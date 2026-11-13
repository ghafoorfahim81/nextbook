<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
        ])->validateWithBag('updateProfileInformation');

        if (isset($input['photo'])) {
            $this->storeSquarePhoto($user, $input['photo']);
        }

        if ($input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $input);
        } else {
            $user->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
            ])->save();
        }
    }

    /**
     * Store the photo as a 256x256 transparent-padded square PNG. The whole picture
     * is kept (scaled to fit, never cropped), so logos and wide or tall images show
     * completely inside the round avatar instead of being cut by object-cover.
     */
    protected function storeSquarePhoto(User $user, UploadedFile $photo): void
    {
        $size = 256;
        $source = @imagecreatefromstring((string) file_get_contents($photo->getRealPath()));

        if ($source === false) {
            $user->updateProfilePhoto($photo);

            return;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min($size / $width, $size / $height);
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagealphablending($canvas, true);
        imagecopyresampled(
            $canvas, $source,
            intdiv($size - $newWidth, 2), intdiv($size - $newHeight, 2), 0, 0,
            $newWidth, $newHeight, $width, $height
        );

        ob_start();
        imagepng($canvas);
        $png = (string) ob_get_clean();

        $disk = config('jetstream.profile_photo_disk', 'public');
        $path = 'profile-photos/'.Str::random(40).'.png';
        Storage::disk($disk)->put($path, $png, 'public');

        $previous = $user->profile_photo_path;
        $user->forceFill(['profile_photo_path' => $path])->save();

        if ($previous) {
            Storage::disk($disk)->delete($previous);
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}
