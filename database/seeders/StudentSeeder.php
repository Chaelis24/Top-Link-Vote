<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Jobs\ImportStudentsJob;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('csv/sample-student.csv');

        if (!file_exists($path)) {
            $this->command->error("CSV file not found at: {$path}");
            return;
        }

        $rows = [];
        if (($handle = fopen($path, 'r')) !== false) {
            $headers = fgetcsv($handle, 1000, ',');

            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $rows[] = array_combine($headers, $data);
            }
            fclose($handle);
        }

        if (!empty($rows)) {
            $this->command->info('Dispatching ImportStudentsJob from Seeder...');

            ImportStudentsJob::dispatchSync($rows);

            $this->command->info('Seeding completed successfully!');
        }
    }
}
