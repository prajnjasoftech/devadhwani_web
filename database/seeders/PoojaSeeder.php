<?php

namespace Database\Seeders;

use App\Models\Pooja;
use App\Models\Temple;
use Illuminate\Database\Seeder;

class PoojaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $temple = Temple::first();
        if (!$temple) {
            $this->command->error('No temple found. Please create a temple first.');
            return;
        }

        // Poojas that require devotee details (pushpanjali, nakshathra pooja, etc.)
        $devoteeRequiredPoojas = [
            'പുഷ്പാഞ്ജലി',
            'ജന്മനക്ഷത്രപൂജ',
            'ഭാഗ്യസൂക്തം',
            'ശ്രീസൂക്തം',
            'പുരുഷസൂക്തം',
            'സഹസ്രനാമം',
            'മൂലമന്ത്രം',
            'മഹാസുദര്ശനം',
            'സാരസ്വതം',
            'സന്താന ഗോപാല മന്ത്രം',
        ];

        $poojas = [
            ['name' => 'പുഷ്പാഞ്ജലി', 'amount' => 10.00],
            ['name' => 'നെയ്‌വിളക്ക്', 'amount' => 15.00],
            ['name' => 'പായസം', 'amount' => 100.00],
            ['name' => 'ഗണപതിഹോമം', 'amount' => 100.00],
            ['name' => 'ബ്രഹ്മരക്ഷസ് പൂജ', 'amount' => 100.00],
            ['name' => 'ആയില്യപൂജ', 'amount' => 100.00],
            ['name' => 'ദുർഗാപൂജ', 'amount' => 100.00],
            ['name' => 'വെണ്ണ നിവേദ്യം', 'amount' => 25.00],
            ['name' => 'കദളി നിവേദ്യം', 'amount' => 25.00],
            ['name' => 'പാലാഭിഷേകം', 'amount' => 50.00],
            ['name' => 'ജന്മനക്ഷത്രപൂജ', 'amount' => 150.00],
            ['name' => 'ഭാഗ്യസൂക്തം', 'amount' => 20.00],
            ['name' => 'ശ്രീസൂക്തം', 'amount' => 20.00],
            ['name' => 'പുരുഷസൂക്തം', 'amount' => 20.00],
            ['name' => 'സഹസ്രനാമം', 'amount' => 50.00],
            ['name' => 'ദിവസപൂജ', 'amount' => 400.00],
            ['name' => 'അപ്പം 1 കൂട്', 'amount' => 3000.00],
            ['name' => 'അപ്പം 1/2 കൂട്', 'amount' => 1500.00],
            ['name' => 'അപ്പം 1/4 കൂട്', 'amount' => 1000.00],
            ['name' => 'നിറമാല I', 'amount' => 3500.00],
            ['name' => 'നിറമാല II', 'amount' => 1500.00],
            ['name' => 'നിറമാല III', 'amount' => 1000.00],
            ['name' => 'ഉദയാസ്തമന പൂജ', 'amount' => 15000.00],
            ['name' => 'അഷ്ടാഭിഷേകം', 'amount' => 10000.00],
            ['name' => 'മാല', 'amount' => 15.00],
            ['name' => 'തുളസി മാല', 'amount' => 20.00],
            ['name' => 'താമരമാല', 'amount' => 400.00],
            ['name' => 'വാരം ഇലക്കൽ', 'amount' => 1500.00],
            ['name' => 'വിവാഹം', 'amount' => 1000.00],
            ['name' => 'ചോറുണ്', 'amount' => 100.00],
            ['name' => 'തൃമധുരം', 'amount' => 50.00],
            ['name' => 'കളഭാഭിഷേകം', 'amount' => 20000.00],
            ['name' => 'ചന്ദനം ചാർത്ത്', 'amount' => 5000.00],
            ['name' => 'ചന്ദനം ചാർത്ത് (മുഴുവൻ)', 'amount' => 500.00],
            ['name' => 'വാഹന പൂജ', 'amount' => 100.00],
            ['name' => 'തിരുവോണം പൂജ', 'amount' => 150.00],
            ['name' => 'തിരുവോണം ഊട്ട്', 'amount' => 5000.00],
            ['name' => '1 കുടം പായസം', 'amount' => 1600.00],
            ['name' => 'മൂലമന്ത്രം', 'amount' => 20.00],
            ['name' => 'മഹാസുദര്ശനം', 'amount' => 20.00],
            ['name' => 'സാരസ്വതം', 'amount' => 20.00],
            ['name' => 'സന്താന ഗോപാല മന്ത്രം', 'amount' => 20.00],
            ['name' => 'ലക്ഷ്മീനാരായണപൂജ', 'amount' => 751.00],
            ['name' => 'അപ്പം, അട (നാഴി)', 'amount' => 200.00],
            ['name' => 'ചതുശ്ശതം', 'amount' => 250.00],
            ['name' => 'നെയ്‌പായസം', 'amount' => 150.00],
            ['name' => 'കറുക മാല', 'amount' => 20.00],
            ['name' => 'നാരങ്ങാ മാല', 'amount' => 100.00],
            ['name' => 'ഒറ്റപ്പം', 'amount' => 50.00],
            ['name' => 'കടുംപായസം', 'amount' => 150.00],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($poojas as $poojaData) {
            // Check if pooja already exists
            $exists = Pooja::where('temple_id', $temple->id)
                ->where('name', $poojaData['name'])
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            Pooja::create([
                'temple_id' => $temple->id,
                'deity_id' => null,
                'name' => $poojaData['name'],
                'amount' => $poojaData['amount'],
                'frequency' => 'once',
                'devotee_required' => in_array($poojaData['name'], $devoteeRequiredPoojas),
                'is_active' => true,
            ]);
            $created++;
        }

        $this->command->info("Poojas seeded: {$created} created, {$skipped} skipped (already exist)");
    }
}
