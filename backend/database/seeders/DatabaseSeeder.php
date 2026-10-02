<?php

namespace Database\Seeders;

use App\Models\BusinessSetting;
use App\Models\Courier;
use App\Models\Owner;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (['CUSTOMER', 'OWNER', 'COURIER'] as $name) {
            Role::firstOrCreate(['name' => $name]);
        }
        $ownerRole = Role::where('name', 'OWNER')->first();
        $courierRole = Role::where('name', 'COURIER')->first();
        $owner = User::firstOrCreate(['email' => 'owner@berkah.test'], ['name' => 'Owner Berkah', 'password' => 'password', 'role_id' => $ownerRole->id]);
        Owner::firstOrCreate(['user_id' => $owner->id]);
        $courier = User::firstOrCreate(['email' => 'courier@kyusui.test'], ['name' => 'Demo Courier', 'password' => 'password', 'role_id' => $courierRole->id]);
        Courier::firstOrCreate(['user_id' => $courier->id, 'phone' => '0800000000']);
        foreach ([['name' => 'Galon 19L', 'description' => 'Air minum isi ulang', 'price' => 15000], ['name' => 'Galon Le Minerale', 'description' => 'Air mineral', 'price' => 22000]] as $p) {
            Product::firstOrCreate(['name' => $p['name']], $p);
        }
        BusinessSetting::firstOrCreate(['key' => 'qris_image_path'], ['value' => 'qris/active.png']);
    }
}
