<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\TravelIdea;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Alex Zhang', 'email' => 'zhangsan@example.com'],
            ['name' => 'Leo Li', 'email' => 'lisi@example.com'],
            ['name' => 'Will Wang', 'email' => 'wangwu@example.com'],
        ];

        $created = [];
        foreach ($users as $u) {
            $created[] = User::create([
                'name' => $u['name'],
                'email' => $u['email'],
                'password' => bcrypt('123456'),
            ]);
        }

        $destinations = [
            ['Tokyo', 'Tokyo Cherry Blossom Season Trip', 'Planning a spring trip to Tokyo to enjoy cherry blossoms, and I want to visit Asakusa, Ueno, and Shinjuku.', '2025-04-01', 'Cherry Blossom,Independent Travel,Food'],
            ['Paris', 'Seven Days in Paris', 'Eiffel Tower, Louvre Museum, and a Seine river cruise to experience the romance of Paris.', '2025-06-15', 'Art,Museum,Romance'],
            ['New York', 'New York City Adventure', 'Times Square, Central Park, and the Statue of Liberty for a classic NYC experience.', '2025-08-01', 'City,Shopping,Landmarks'],
            ['Beijing', 'Forbidden City and Great Wall', 'A cultural history trip including the Forbidden City, Great Wall, and traditional hutong food.', '2025-10-01', 'History,Culture,Food'],
            ['Shanghai', 'Shanghai Weekend Getaway', 'The Bund, Tianzifang, and Disneyland in a two-day city break.', null, 'Weekend,Family,Disneyland'],
        ];

        foreach ($destinations as $i => $d) {
            $idea = TravelIdea::create([
                'user_id' => $created[$i % 3]->id,
                'destination' => $d[0],
                'title' => $d[1],
                'description' => $d[2],
                'start_date' => $d[3],
                'end_date' => $d[3],
                'travel_date' => $d[3],
                'tags' => $d[4],
                'cover_image' => null,
                'is_public' => true,
            ]);

            Comment::create([
                'travel_idea_id' => $idea->id,
                'user_id' => $created[($i + 1) % 3]->id,
                'content' => 'Looks great, saved this idea!',
            ]);
        }

        Comment::create([
            'travel_idea_id' => 1,
            'user_id' => $created[2]->id,
            'content' => 'Tokyo is beautiful in spring. Meguro River is highly recommended.',
        ]);
    }
}
