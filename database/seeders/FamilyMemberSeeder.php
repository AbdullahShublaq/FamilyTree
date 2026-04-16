<?php

namespace Database\Seeders;

use App\Models\FamilyMember;
use Illuminate\Database\Seeder;

class FamilyMemberSeeder extends Seeder
{
    private const ARABIC_NAMES = [
        'عمر', 'محمد', 'محمود', 'أحمد', 'علي', 'حسن', 'حسين', 'إبراهيم', 'يوسف', 'خالد',
        'سعيد', 'فهد', 'عبدالله', 'سلطان', 'طارق', 'وليد', 'زياد', 'بدر', 'ناصر', 'راشد',
        'سالم', 'حمد', 'جاسم', 'مبارك', 'عيسى', 'ياسر', 'أنس', 'بلال', 'عمار', 'حاتم',
        'ماجد', 'نادر', 'رامي', 'سمير', 'كريم', 'طه', 'عبدالرحمن', 'زكريا', 'إسماعيل',
        'عبدالعزيز', 'فيصل', 'مشعل', 'نواف', 'بندر', 'تركي', 'سعود', 'منصور', 'عادل', 'صلاح',
        'لواء', 'هشام', 'غانم', 'راغب', 'صالح', 'نعيم', 'عامر', 'فوزي', 'مصطفى', 'شاكر',
    ];

    private int $nameIndex = 0;

    public function run(): void
    {
        FamilyMember::truncate();
        $this->nameIndex = 0;

        $grandfather = $this->createMember(null);
        $this->buildChain($grandfather->id, 2, 10);
    }

    private function buildChain(int $parentId, int $currentLevel, int $maxLevel): void
    {
        if ($currentLevel > $maxLevel) {
            return;
        }

        $chainChild = $this->createMember($parentId);

        $siblingCount = $currentLevel <= 3 ? rand(1, 3) : 1;
        for ($i = 0; $i < $siblingCount; $i++) {
            $sibling = $this->createMember($parentId);
            if ($currentLevel < $maxLevel && rand(0, 1) === 1) {
                $grandchildCount = rand(1, 2);
                for ($j = 0; $j < $grandchildCount; $j++) {
                    $this->createMember($sibling->id);
                }
            }
        }

        $this->buildChain($chainChild->id, $currentLevel + 1, $maxLevel);
    }

    private function createMember(?int $parentId): FamilyMember
    {
        $name = self::ARABIC_NAMES[$this->nameIndex % count(self::ARABIC_NAMES)];
        $this->nameIndex++;

        return FamilyMember::create([
            'name' => $name,
            'gender' => 'male',
            'birth_date' => null,
            'death_date' => null,
            'wife_name' => null,
            'description' => null,
            'parent_id' => $parentId,
        ]);
    }
}
