<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6" enctype="multipart/form-data" id="profile-form">
        @csrf
        @method('patch')

        <input type="hidden" name="cropped_photo" id="cropped_photo">

        <div>
            <x-input-label for="photo" :value="__('Profile Photo')" />
            <div class="mt-2 flex items-center space-x-4">
                <div class="shrink-0">
                    @if ($user->profile_photo_path)
                        <img id="avatar-preview" class="h-16 w-16 object-cover rounded-full border border-gray-200 dark:border-gray-700 shadow-sm" src="{{ asset('storage/' . $user->profile_photo_path) }}" alt="{{ $user->name }}" />
                    @else
                        <div id="avatar-placeholder" class="h-16 w-16 rounded-full bg-gradient-to-r from-red-500 to-purple-600 flex items-center justify-center text-white font-bold text-2xl shadow-sm">
                            {{ substr($user->name, 0, 1) }}
                        </div>
                    @endif
                </div>
                <label class="block">
                    <span class="sr-only">Choose profile photo</span>
                    <input type="file" id="photo-input" accept="image/*" class="block w-full text-sm text-gray-500 dark:text-gray-400
                        file:mr-4 file:py-2 file:px-4
                        file:rounded-full file:border-0
                        file:text-sm file:font-semibold
                        file:bg-red-50 file:text-red-700
                        hover:file:bg-red-100
                        dark:file:bg-red-900/20 dark:file:text-red-400
                        cursor-pointer transition-all
                    "/>
                </label>
            </div>
            <p class="mt-1 text-xs text-gray-500">Square images work best. Max 2MB.</p>
            <x-input-error class="mt-2" :messages="$errors->get('photo')" />
        </div>

        <!-- Crop Modal -->
        <div id="crop-modal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 bg-black/80 backdrop-blur-md">
            <div class="glass-card w-full max-w-2xl overflow-hidden rounded-3xl border border-white/20 shadow-2xl flex flex-col">
                <div class="p-4 border-b border-white/10 flex justify-between items-center bg-white/5">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Sesuaikan Foto</h3>
                    <button type="button" onclick="closeCropModal()" class="text-gray-500 hover:text-gray-700 dark:hover:text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-6 overflow-hidden flex flex-col items-center">
                    <div class="max-h-[60vh] w-full flex justify-center bg-black/40 rounded-xl overflow-hidden">
                        <img id="crop-image" src="" class="max-w-full block">
                    </div>
                </div>
                <div class="p-4 border-t border-white/10 flex justify-end space-x-3 bg-white/5">
                    <button type="button" onclick="closeCropModal()" class="px-5 py-2 rounded-xl border border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-300 hover:bg-white/10 transition-all font-medium">Batal</button>
                    <button type="button" onclick="applyCrop()" class="px-6 py-2 rounded-xl bg-red-600 text-white font-bold shadow-lg shadow-red-500/30 hover:shadow-red-500/50 transition-all transform hover:-translate-y-0.5">Simpan</button>
                </div>
            </div>
        </div>

        <!-- Same field for Name & Email -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800 dark:text-gray-200">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600 dark:text-green-400">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600 dark:text-gray-400"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>

    <script>
        let cropper;
        const photoInput = document.getElementById('photo-input');
        const cropModal = document.getElementById('crop-modal');
        const cropImage = document.getElementById('crop-image');
        const croppedPhotoInput = document.getElementById('cropped_photo');
        const avatarPreview = document.getElementById('avatar-preview');
        const avatarPlaceholder = document.getElementById('avatar-placeholder');

        photoInput.addEventListener('change', function(e) {
            const files = e.target.files;
            if (files && files.length > 0) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    cropImage.src = e.target.result;
                    cropModal.classList.remove('hidden');
                    cropModal.classList.add('flex');
                    
                    if (cropper) {
                        cropper.destroy();
                    }
                    
                    cropper = new Cropper(cropImage, {
                        aspectRatio: 1,
                        viewMode: 1,
                        guides: true,
                    });
                };
                reader.readAsDataURL(files[0]);
            }
        });

        function closeCropModal() {
            cropModal.classList.add('hidden');
            cropModal.classList.remove('flex');
            photoInput.value = '';
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
        }

        function applyCrop() {
            if (!cropper) return;
            
            const canvas = cropper.getCroppedCanvas({
                width: 400,
                height: 400,
            });
            
            const base64data = canvas.toDataURL('image/jpeg', 0.8);
            croppedPhotoInput.value = base64data;
            
            // UI Update preview
            if (avatarPreview) {
                avatarPreview.src = base64data;
            } else if (avatarPlaceholder) {
                // If no preview image yet, create one
                const img = document.createElement('img');
                img.id = 'avatar-preview';
                img.className = 'h-16 w-16 object-cover rounded-full border border-gray-200 dark:border-gray-700 shadow-sm';
                img.src = base64data;
                avatarPlaceholder.parentNode.replaceChild(img, avatarPlaceholder);
            }
            
            cropModal.classList.add('hidden');
            cropModal.classList.remove('flex');
        }
    </script>
</section>
