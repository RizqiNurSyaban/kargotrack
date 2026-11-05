<?php
namespace Models;

use Core\Model;

/**
 * Model User (Otentikasi & Petugas)
 * Mengimplementasikan OOP Encapsulation & password_verify
 */
class User extends Model
{
    protected string $table = 'users';

    /**
     * Cari user berdasarkan email
     */
    public function findByEmail(string $email): ?array
    {
        return $this->firstWhere(['email' => $email]);
    }

    /**
     * Verifikasi Kredensial Login
     */
    public function verifyCredentials(string $email, string $password): ?array
    {
        $user = $this->findByEmail($email);
        if (!$user) {
            return null;
        }

        // Verifikasi password hash
        if (password_verify($password, $user['password'])) {
            // Sembunyikan password hash dari object user yang disimpan di sesi
            unset($user['password']);
            return $user;
        }

        return null;
    }
}

