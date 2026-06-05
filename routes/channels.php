<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('election-results.{courseId}', function ($user, $courseId) {
    $userCourse = $user->student?->block?->course?->name;
    return strtoupper((string) $userCourse) === strtoupper((string) $courseId);
});

Broadcast::channel('admin.audit-trail', function ($user) {
    return $user->hasRole('admin');
});
