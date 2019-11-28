<?php

use Illuminate\Database\Seeder;
use App\User;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = new User();
        $user->email = 'admin@gmail.com';
        $user->name = 'Admin';
        $user->email_verified_at = Date('Y-m-d H:i:s');
        $user->password = bcrypt('adminpassword');
        $user->save();
    }
}
