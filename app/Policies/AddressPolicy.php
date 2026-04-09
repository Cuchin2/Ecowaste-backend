<?php

namespace App\Policies;

use App\Models\Address;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AddressPolicy
{
        public function update(User $user, Address $address)
        {
            return $user->id === $address->user_id;
        }

        public function delete(User $user, Address $address)
        {
            return $user->id === $address->user_id;
        }
        public function setDefault(User $user, Address $address)
        {
            return $user->id === $address->user_id;
        }
}
