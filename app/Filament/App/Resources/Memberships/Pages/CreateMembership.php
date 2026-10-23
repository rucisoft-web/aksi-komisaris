<?php

namespace App\Filament\App\Resources\Memberships\Pages;

use App\Filament\App\Resources\Memberships\MembershipResource;
use App\Models\Membership;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateMembership extends CreateRecord
{
    protected static string $resource = MembershipResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = User::query()->where('email', $data['email'])->first();

        if (! $user) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'] ?: Str::password(16),
            ]);
        }

        if (Membership::query()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages(['data.email' => 'Pengguna ini sudah terdaftar di perusahaan ini.']);
        }

        unset($data['email'], $data['name'], $data['password']);

        return parent::handleRecordCreation($data + ['user_id' => $user->id]);
    }
}
