<?php

namespace Database\Seeders;

use App\Models\Nakshathra;
use Illuminate\Database\Seeder;

class NakshatharaSeeder extends Seeder
{
    public function run(): void
    {
        $nakshathras = [
            ['name' => 'Ashwini', 'malayalam_name' => 'അശ്വതി', 'order' => 1],
            ['name' => 'Bharani', 'malayalam_name' => 'ഭരണി', 'order' => 2],
            ['name' => 'Krittika', 'malayalam_name' => 'കാർത്തിക', 'order' => 3],
            ['name' => 'Rohini', 'malayalam_name' => 'രോഹിണി', 'order' => 4],
            ['name' => 'Mrigashira', 'malayalam_name' => 'മകയിരം', 'order' => 5],
            ['name' => 'Ardra', 'malayalam_name' => 'തിരുവാതിര', 'order' => 6],
            ['name' => 'Punarvasu', 'malayalam_name' => 'പുനർതം', 'order' => 7],
            ['name' => 'Pushya', 'malayalam_name' => 'പൂയം', 'order' => 8],
            ['name' => 'Ashlesha', 'malayalam_name' => 'ആയില്യം', 'order' => 9],
            ['name' => 'Magha', 'malayalam_name' => 'മകം', 'order' => 10],
            ['name' => 'Purva Phalguni', 'malayalam_name' => 'പൂരം', 'order' => 11],
            ['name' => 'Uttara Phalguni', 'malayalam_name' => 'ഉത്രം', 'order' => 12],
            ['name' => 'Hasta', 'malayalam_name' => 'അത്തം', 'order' => 13],
            ['name' => 'Chitra', 'malayalam_name' => 'ചിത്തിര', 'order' => 14],
            ['name' => 'Swati', 'malayalam_name' => 'ചോതി', 'order' => 15],
            ['name' => 'Vishakha', 'malayalam_name' => 'വിശാഖം', 'order' => 16],
            ['name' => 'Anuradha', 'malayalam_name' => 'അനിഴം', 'order' => 17],
            ['name' => 'Jyeshtha', 'malayalam_name' => 'തൃക്കേട്ട', 'order' => 18],
            ['name' => 'Mula', 'malayalam_name' => 'മൂലം', 'order' => 19],
            ['name' => 'Purva Ashadha', 'malayalam_name' => 'പൂരാടം', 'order' => 20],
            ['name' => 'Uttara Ashadha', 'malayalam_name' => 'ഉത്രാടം', 'order' => 21],
            ['name' => 'Shravana', 'malayalam_name' => 'തിരുവോണം', 'order' => 22],
            ['name' => 'Dhanishta', 'malayalam_name' => 'അവിട്ടം', 'order' => 23],
            ['name' => 'Shatabhisha', 'malayalam_name' => 'ചതയം', 'order' => 24],
            ['name' => 'Purva Bhadrapada', 'malayalam_name' => 'പൂരുരുട്ടാതി', 'order' => 25],
            ['name' => 'Uttara Bhadrapada', 'malayalam_name' => 'ഉത്രട്ടാതി', 'order' => 26],
            ['name' => 'Revati', 'malayalam_name' => 'രേവതി', 'order' => 27],
        ];

        foreach ($nakshathras as $nakshathra) {
            Nakshathra::firstOrCreate(
                ['name' => $nakshathra['name']],
                $nakshathra
            );
        }

        $this->command->info('27 Nakshathras seeded successfully!');
    }
}
