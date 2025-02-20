<?php

namespace App\Repositories;

use Shared\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class UserRepository implements UserRepositoryInterface
{
  /**
   * The model instance.
   *
   * @var User
   */
  protected $model;

  public function __construct(User $user)
  {
    $this->model = $user;
  }

  /**
   * Create a new user.
   *
   * @param array<string, string> $data
   * @return User
   */
  public function store(array $data)
  {
    return $this->model->create([
      "name" => $data["name"],
      "email" => $data["email"],
      "password" => Hash::make($data["password"]),
      "role" => $data["role"] ?? "user",
    ]);
  }

  /**
   * Find a user by email.
   *
   * @param string $email
   * @return User
   */
  public function findByEmail(string $email)
  {
    return $this->model->where("email", $email)->first();
  }

  /**
   * Find a user by id.
   *
   * @param int $id
   * @return User
   */
  public function findById(int $id)
  {
    return $this->model->find($id);
  }
}
