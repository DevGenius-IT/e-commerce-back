<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Shared\Models\User as SharedUser;

class User extends SharedUser
{
  use HasFactory, Notifiable, SoftDeletes;
}
