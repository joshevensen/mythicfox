<?php

namespace Database\Factories;

use App\Models\File;
use App\Support\FilePath;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    protected $model = File::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = fake()->randomElement([
            'OrderList.csv',
            'PullSheet.csv',
            'ShippingExport.csv',
            'PackingSlips.pdf',
        ]);

        return [
            'type' => 'import',
            'file_path' => FilePath::build('imports', 'orders', $filename),
            'original_filename' => $filename,
            'uploaded_at' => Carbon::now(),
            'expired_at' => null,
        ];
    }
}
