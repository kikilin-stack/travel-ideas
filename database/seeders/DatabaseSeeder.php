<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\TravelIdea;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * 填充测试用户、旅行想法和评论
     */
    public function run(): void
    {
        $users = [
            ['name' => '张三', 'email' => 'zhangsan@example.com'],
            ['name' => '李四', 'email' => 'lisi@example.com'],
            ['name' => '王五', 'email' => 'wangwu@example.com'],
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
            ['东京', '东京樱花季之旅', '计划春天去东京看樱花，浅草、上野、新宿都想去。', '2025-04-01', '樱花,自由行,美食'],
            ['巴黎', '巴黎七日游', '埃菲尔铁塔、卢浮宫、塞纳河游船，感受浪漫之都。', '2025-06-15', '艺术,博物馆,浪漫'],
            ['纽约', '纽约都市行', '时代广场、中央公园、自由女神像，体验大苹果。', '2025-08-01', '都市,购物,打卡'],
            ['北京', '故宫与长城', '历史文化之旅，故宫、长城、胡同小吃。', '2025-10-01', '历史,文化,美食'],
            ['上海', '魔都周末游', '外滩、田子坊、迪士尼，周末两日游。', null, '周末,亲子,迪士尼'],
        ];

        foreach ($destinations as $i => $d) {
            $idea = TravelIdea::create([
                'user_id' => $created[$i % 3]->id,
                'destination' => $d[0],
                'title' => $d[1],
                'description' => $d[2],
                'travel_date' => $d[3],
                'tags' => $d[4],
                'cover_image' => null,
                'is_public' => true,
            ]);
            Comment::create([
                'travel_idea_id' => $idea->id,
                'user_id' => $created[($i + 1) % 3]->id,
                'content' => '看起来很棒，收藏了！',
            ]);
        }

        Comment::create([
            'travel_idea_id' => 1,
            'user_id' => $created[2]->id,
            'content' => '东京春天确实很美，推荐去目黑川。',
        ]);
    }
}
