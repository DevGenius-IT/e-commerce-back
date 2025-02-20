<?php

namespace Shared\Interfaces;

interface RepositoryInterface
{
  public function store(array $data);
  public function findByEmail(string $email);
  public function findById(int $id);
}
