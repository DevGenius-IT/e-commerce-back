<?php

namespace App\Repositories\Interfaces;

use Shared\Interfaces\RepositoryInterface;

interface UserRepositoryInterface extends RepositoryInterface
{
  public function findByEmail(string $email);
}
