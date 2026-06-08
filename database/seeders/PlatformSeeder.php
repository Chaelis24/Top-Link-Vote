<?php

namespace Database\Seeders;

use App\Models\Candidate;
use App\Models\Platform;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PlatformSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $candidates = Candidate::all();

        foreach ($candidates as $candidate) {
            $titles = ['Vision for Progress', 'Empowering Students', 'New Chapter', 'Student-First Agenda'];
            $taglines = [
                'Committed to service and excellence.',
                'Your voice, our action.',
                'Building a better campus together.',
                'Integrity in every decision.'
            ];
            $agendas = [
                ['Improve student facilities', 'Transparent budget allocation', 'Enhanced student welfare'],
                ['Digital transformation', 'More sports activities', 'Inclusive student governance'],
                ['Sustainability projects', 'Affordable services', 'Student career support'],
                ['Campus safety', 'Better communication', 'Mental health awareness']
            ];

            Platform::updateOrCreate(
                ['candidate_id' => $candidate->id],
                [
                    'title'        => $titles[array_rand($titles)] . ' (' . $candidate->party_name . ')',
                    'tagline'      => $taglines[array_rand($taglines)],
                    'agenda'       => $agendas[array_rand($agendas)],
                    'status'       => 'approved',
                    'submitted_at' => Carbon::now(),
                    'approved_at'  => Carbon::now(),
                ]
            );
        }
    }
}
