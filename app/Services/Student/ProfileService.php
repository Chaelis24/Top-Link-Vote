<?php

namespace App\Services\Student;

use App\Models\{Setting, ElectionCycle, User};
use Illuminate\Support\Facades\{Hash, Storage, DB, Log};
use Illuminate\Http\UploadedFile;

/**
 * Service for managing the student profile.
 *
 * Handles profile data retrieval, profile photo uploads,
 * contact information updates, and password changes.
 */
class ProfileService
{
    /**
     * Load the full profile data for the authenticated user.
     *
     * Merges user-level data with the associated student record,
     * including course, year level, voting status, and profile photo.
     *
     * @param  \App\Models\User  $user
     * @return array
     */
    public function getProfileData(User $user): array
    {
        $user->load('student.block');
        $profile = $user->student;

        $activeCycle = ElectionCycle::where('status', 'active')->first();
        $settings = Setting::pluck('value', 'key')->toArray();
        $isSettingOpen = (bool) ($settings['allowVoting'] ?? false);
        $isDateValid = $activeCycle && now()->lte($activeCycle->voting_end);

        $data = [
            'name' => $user->name ?? '',
            'email' => $user->email ?? '',
            'isVotingOpen' => $isSettingOpen && $isDateValid,
        ];

        if ($profile) {
            $data = array_merge($data, [
                'user_id' => $profile->user_id,
                'student_id' => $profile->student_id,
                'first_name' => $profile->first_name,
                'middle_name' => $profile->middle_name,
                'last_name' => $profile->last_name,
                'suffix' => $profile->suffix,
                'course' => $profile->block?->course?->name ?? 'N/A',
                'year_level' => $profile->block?->year_level ?? 'N/A',
                'block_section' => $profile->block?->section ?? '',
                'status' => $profile->status,
                'phone' => $profile->phone,
                'address' => $profile->address,
                'birthday' => $profile->birthday ? $profile->birthday->format('Y-m-d') : '',
                'gender' => $profile->gender,
                'profile_photo_path' => $profile->photo,
                'has_voted' => (bool) $profile->has_voted,
                'voted_at' => $profile->voted_at,
            ]);
        }

        return $data;
    }

    /**
     * Update the student's profile information.
     *
     * Supports updating the email on the user record, and the phone
     * number and profile photo on the student record. Deletes the
     * old photo from storage when replaced.
     *
     * @param  \App\Models\User  $user
     * @param  array  $data
     * @param  \Illuminate\Http\UploadedFile|null  $photo
     * @return array
     */
    public function updateProfile(User $user, array $data, ?UploadedFile $photo): array
    {
        try {
            DB::beginTransaction();

            if (!empty($data['email'])) {
                $user->update(['email' => $data['email']]);
            }

            if ($user->student) {
                $studentData = ['phone' => $data['phone'] ?? ''];

                if ($photo) {
                    if ($user->student->photo) {
                        Storage::disk('public')->delete($user->student->photo);
                    }
                    $path = $photo->store('student-profile', 'public');
                    $studentData['photo'] = $path;
                }

                $user->student->update($studentData);
            }

            DB::commit();

            return ['success' => true, 'profile_photo_path' => $user->student->photo ?? null];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Profile Save Error: ' . $e->getMessage());
            return ['error' => 'Something went wrong while saving your profile.'];
        }
    }

    /**
     * Update the user's password with a new hashed value.
     *
     * @param  \App\Models\User  $user
     * @param  string  $newPassword
     * @return void
     */
    public function updatePassword(User $user, string $newPassword): void
    {
        $user->update([
            'password' => Hash::make($newPassword),
        ]);
    }
}
