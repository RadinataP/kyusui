<?php

namespace Database\Seeders;

use App\Models\BusinessSetting;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\Owner;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Seed roles
        foreach (['CUSTOMER', 'OWNER', 'COURIER'] as $name) {
            Role::firstOrCreate(
                ['name' => $name],
                ['display_name' => Role::defaultDisplayName($name)],
            );
        }

        $customerRole = Role::where('name', 'CUSTOMER')->first();
        $ownerRole = Role::where('name', 'OWNER')->first();
        $courierRole = Role::where('name', 'COURIER')->first();

        // Seed owner
        $owner = User::firstOrCreate(
            ['email' => 'owner@berkah.test'],
            [
                'name' => 'Owner Berkah',
                'password' => Hash::make('password'),
                'role_id' => $ownerRole->id,
            ]
        );
        Owner::firstOrCreate(['user_id' => $owner->id]);

        // Seed courier
        $courier = User::firstOrCreate(
            ['email' => 'courier@kyusui.test'],
            [
                'name' => 'Demo Courier',
                'password' => Hash::make('password'),
                'role_id' => $courierRole->id,
            ]
        );
        Courier::firstOrCreate(
            ['user_id' => $courier->id],
            [
                'phone' => '0800000000',
                'vehicle' => 'Motor',
            ]
        );

        // Seed customer
        $customer = User::firstOrCreate(
            ['email' => 'customer@kyusui.test'],
            [
                'name' => 'Demo Customer',
                'password' => Hash::make('password'),
                'role_id' => $customerRole->id,
            ]
        );
        Customer::firstOrCreate(
            ['user_id' => $customer->id],
            [
                'phone' => '0811111111',
                'address' => 'Jl. Demo No. 123',
            ]
        );

        // Seed products
        foreach ([
            ['name' => 'Galon 19L', 'description' => 'Air minum isi ulang', 'price' => 15000],
            ['name' => 'Galon Le Minerale', 'description' => 'Air mineral', 'price' => 22000],
        ] as $p) {
            Product::firstOrCreate(['name' => $p['name']], $p);
        }

        // Seed konfigurasi singleton (13_DB section 21.2).
        BusinessSetting::query()->updateOrCreate(
            ['id' => BusinessSetting::SINGLETON_ID],
            ['qris_image' => 'qris/active.png'],
        );
    }
}
