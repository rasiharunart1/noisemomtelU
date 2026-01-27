<x-guest-layout>
    <!-- Title -->
    <div class="mb-6 text-center">
        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Welcome Back!</h2>
        <p class="text-sm text-gray-600 dark:text-gray-400">Sign in to your account</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-6">
        @csrf

        <!-- Email Address -->
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Email Address
            </label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900 dark:text-white placeholder-gray-500 transition-all">
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Password
            </label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-900 dark:text-white placeholder-gray-500 transition-all">
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="flex items-center">
            <input id="remember_me" type="checkbox" name="remember" 
                   class="w-4 h-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500 bg-white/10">
            <label for="remember_me" class="ml-2 text-sm text-gray-600 dark:text-gray-300">
                Remember me
            </label>
        </div>

        <!-- Buttons -->
        <div class="space-y-4">
            <button type="submit" class="w-full btn-primary">
                Sign In
            </button>

            <div class="flex items-center justify-between text-sm">
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" 
                       class="text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-300 font-medium">
                        Forgot password?
                    </a>
                @endif

                @if (Route::has('register'))
                    <a href="{{ route('register') }}" 
                       class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white font-medium">
                        Create account
                    </a>
                @endif
            </div>
        </div>
    </form>
</x-guest-layout>
