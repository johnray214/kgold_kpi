<?php

namespace App\Http\Controllers;

use App\Console\Commands\KpiSync;
use App\Models\Page;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    private function sharedNav(Request $request): array
    {
        /** @var User $me */
        $me = Auth::user();
        $tz = config('kpi.timezone');
        $allPages = \Illuminate\Support\Facades\Cache::remember('kpi:all_pages', 900, fn () => DashboardController::lightPagesQuery()->get());
        $pages = $me->isAdmin()
            ? $allPages
            : $allPages->whereIn('id', \Illuminate\Support\Facades\Cache::remember("kpi:user_pages:{$me->id}", 900, fn () => $me->pages()->pluck('pages.id')->all()))->values();

        $syncMeta = \Illuminate\Support\Facades\Cache::remember('kpi:sync_meta', 180, fn () => [
            'lastSync' => SyncRun::where('status', 'ok')->latest('finished_at')->first(),
            'syncRunning' => SyncRun::where('status', 'running')->where('started_at', '>', now()->subMinutes(15))->exists(),
            'lastError' => SyncRun::whereIn('id', SyncRun::selectRaw('max(id)')->groupBy('task'))
                ->where('status', 'error')->latest('started_at')->first(),
        ]);

        return $syncMeta + [
            'pages' => $pages,
            'allPages' => $allPages,
            'products' => $pages->pluck('product')->filter()->unique()->sort()->values(),
            'teams' => $pages->pluck('team')->filter()->unique()->sort()->values(),
            'product' => $request->input('product'),
            'team' => $request->input('team'),
            'pageId' => $request->input('page', 'all'),
            'from' => $request->input('from', now($tz)->toDateString()),
            'to' => $request->input('to', now($tz)->toDateString()),
        ];
    }

    public function index(Request $request)
    {
        abort_unless(Auth::user()?->isAdmin(), 403, 'Only admins can manage accounts.');

        $accounts = \Illuminate\Support\Facades\Cache::remember('kpi:accounts_list', 900, fn () => User::with(['pages:id,fb_page_id,name'])
            ->orderByRaw("case when role = 'admin' then 0 else 1 end")
            ->orderBy('name')
            ->get()
        );

        return view('accounts', $this->sharedNav($request) + [
            'accounts' => $accounts,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', Rule::in(['admin', 'member'])],
            'page_ids' => ['nullable', 'array'],
            'page_ids.*' => ['integer', 'exists:pages,id'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => true,
        ]);

        $user->pages()->sync($data['page_ids'] ?? []);
        \Illuminate\Support\Facades\Cache::flush();

        return back()->with('status', "Created account for {$user->name} ({$user->email}).");
    }

    public function update(Request $request, User $user)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['required', Rule::in(['admin', 'member'])],
            'is_active' => ['nullable', 'boolean'],
            'page_ids' => ['nullable', 'array'],
            'page_ids.*' => ['integer', 'exists:pages,id'],
        ]);

        $user->name = $data['name'];
        $user->email = strtolower($data['email']);
        // Prevent locking yourself out of admin
        if ($user->id !== Auth::id()) {
            $user->role = $data['role'];
            $user->is_active = $request->boolean('is_active', true);
        }
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        if ($request->has('sync_pages')) {
            $user->pages()->sync($data['page_ids'] ?? []);
        }
        \Illuminate\Support\Facades\Cache::flush();

        return back()->with('status', "Updated account {$user->name}.");
    }

    public function assignPages(Request $request, User $user)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $data = $request->validate([
            'page_ids' => ['nullable', 'array'],
            'page_ids.*' => ['integer', 'exists:pages,id'],
        ]);

        $user->pages()->sync($data['page_ids'] ?? []);
        \Illuminate\Support\Facades\Cache::flush();

        return back()->with('status', "Updated assigned pages for {$user->name} (".count($data['page_ids'] ?? [])." page(s)).");
    }

    public function destroy(User $user)
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        if ($user->id === Auth::id()) {
            return back()->with('status', 'You cannot delete your own logged-in account.');
        }

        $name = $user->name;
        $user->delete();
        \Illuminate\Support\Facades\Cache::flush();

        return back()->with('status', "Removed account {$name}.");
    }

    public function profile(Request $request)
    {
        $nav = $this->sharedNav($request);
        /** @var User $me */
        $me = Auth::user();
        $me->setRelation('pages', $nav['pages']);

        return view('profile', $nav + [
            'me' => $me,
        ]);
    }

    public function updateProfile(Request $request)
    {
        /** @var User $me */
        $me = Auth::user();

        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($me->id)],
        ];

        $changingPassword = filled($request->input('new_password'));
        if ($changingPassword) {
            $rules['current_password'] = ['required', 'current_password'];
            $rules['new_password'] = ['required', 'string', 'min:6', 'confirmed'];
        }

        $data = $request->validate($rules);

        $me->name = $data['name'];
        $me->email = strtolower($data['email']);
        if ($changingPassword) {
            $me->password = Hash::make($data['new_password']);
        }
        $me->save();
        \Illuminate\Support\Facades\Cache::flush();

        return back()->with('status', $changingPassword
            ? 'Profile and password updated.'
            : 'Profile details saved (password unchanged).');
    }
}
