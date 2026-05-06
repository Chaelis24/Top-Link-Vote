<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
Broadcast::channel('election-results.{courseId}', function ($user, $courseId) {
    return (string) $user->student->course === (string) $courseId;
});
