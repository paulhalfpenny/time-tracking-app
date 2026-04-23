<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

class SampleClientSeeder extends Seeder
{
    public function run(): void
    {
        $clients = [
            ['name' => 'JDW Projects', 'code' => 'JDW'],
            ['name' => 'JDW Management', 'code' => null],
            ['name' => 'AAB', 'code' => 'AAB'],
            ['name' => 'Agile Business Consortium', 'code' => 'ABC'],
            ['name' => 'Filter Agency', 'code' => 'FAL'],
        ];

        foreach ($clients as $data) {
            Client::firstOrCreate(['name' => $data['name']], ['code' => $data['code']]);
        }
    }
}
