<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Vehicle;
use App\Models\User;

class VehicleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tạo một số vehicle mẫu
        $vehicles = [
            [
                'rfid_tag' => 'A1B2C3D4',
                'license_plate' => '29A-12345',
                'is_active' => true,
            ],
            [
                'rfid_tag' => 'E5F6G7H8',
                'license_plate' => '30B-67890',
                'is_active' => true,
            ],
            [
                'rfid_tag' => 'I9J0K1L2',
                'license_plate' => '51C-11111',
                'is_active' => true,
            ],
        ];

        foreach ($vehicles as $vehicleData) {
            Vehicle::updateOrCreate(
                ['rfid_tag' => $vehicleData['rfid_tag']],
                $vehicleData
            );
        }
    }
}
