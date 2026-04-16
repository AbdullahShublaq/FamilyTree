<?php

use App\Models\FamilyMember;

describe('Tree Structure', function () {
    test('multiple roots are returned as separate tree nodes', function () {
        FamilyMember::create(['name' => 'عمر', 'gender' => 'male']);
        FamilyMember::create(['name' => 'محمد', 'gender' => 'male']);
        FamilyMember::create(['name' => 'محمود', 'gender' => 'male']);

        $response = $this->getJson('/api/family-tree');

        $response->assertOk()
            ->assertJsonCount(3);
    });

    test('children are nested under correct parent', function () {
        $parent = FamilyMember::create(['name' => 'عمر', 'gender' => 'male']);
        $child1 = FamilyMember::create(['name' => 'أحمد', 'gender' => 'male', 'parent_id' => $parent->id]);
        $child2 = FamilyMember::create(['name' => 'سعيد', 'gender' => 'male', 'parent_id' => $parent->id]);

        $response = $this->getJson('/api/family-tree');

        $tree = $response->json();

        expect($tree[0]['children'])->toHaveCount(2)
            ->and($tree[0]['children'][0]['name'])->toBe('أحمد')
            ->and($tree[0]['children'][1]['name'])->toBe('سعيد');
    });

    test('deeply nested tree structure is built correctly', function () {
        $level1 = FamilyMember::create(['name' => 'جلال', 'gender' => 'male']);
        $level2 = FamilyMember::create(['name' => 'بسام', 'gender' => 'male', 'parent_id' => $level1->id]);
        $level3 = FamilyMember::create(['name' => 'وسام', 'gender' => 'male', 'parent_id' => $level2->id]);
        $level4 = FamilyMember::create(['name' => 'تمام', 'gender' => 'male', 'parent_id' => $level3->id]);
        $level5 = FamilyMember::create(['name' => 'همام', 'gender' => 'male', 'parent_id' => $level4->id]);

        $response = $this->getJson('/api/family-tree');
        $tree = $response->json();

        expect($tree[0]['name'])->toBe('جلال')
            ->and($tree[0]['children'][0]['name'])->toBe('بسام')
            ->and($tree[0]['children'][0]['children'][0]['name'])->toBe('وسام')
            ->and($tree[0]['children'][0]['children'][0]['children'][0]['name'])->toBe('تمام')
            ->and($tree[0]['children'][0]['children'][0]['children'][0]['children'][0]['name'])->toBe('همام');
    });

    test('leaf nodes have empty children array', function () {
        $parent = FamilyMember::create(['name' => 'عمر', 'gender' => 'male']);
        FamilyMember::create(['name' => 'أحمد', 'gender' => 'male', 'parent_id' => $parent->id]);

        $response = $this->getJson('/api/family-tree');
        $tree = $response->json();

        expect($tree[0]['children'][0]['children'])->toHaveCount(0);
    });

    test('root with no children has empty children array', function () {
        FamilyMember::create(['name' => 'وحيد', 'gender' => 'male']);

        $response = $this->getJson('/api/family-tree');
        $tree = $response->json();

        expect($tree[0]['children'])->toHaveCount(0);
    });

    test('multiple branches under same parent', function () {
        $root = FamilyMember::create(['name' => 'عمر', 'gender' => 'male']);

        $branch1 = FamilyMember::create(['name' => 'أحمد', 'gender' => 'male', 'parent_id' => $root->id]);
        $branch2 = FamilyMember::create(['name' => 'سعيد', 'gender' => 'male', 'parent_id' => $root->id]);

        FamilyMember::create(['name' => 'علي', 'gender' => 'male', 'parent_id' => $branch1->id]);
        FamilyMember::create(['name' => 'خالد', 'gender' => 'male', 'parent_id' => $branch1->id]);
        FamilyMember::create(['name' => 'فهد', 'gender' => 'male', 'parent_id' => $branch2->id]);

        $response = $this->getJson('/api/family-tree');
        $tree = $response->json();

        expect($tree[0]['children'])->toHaveCount(2)
            ->and($tree[0]['children'][0]['children'])->toHaveCount(2)
            ->and($tree[0]['children'][1]['children'])->toHaveCount(1);
    });
});

describe('Tree with Soft Deletes', function () {
    test('soft deleted members still appear in tree due to relationship', function () {
        $parent = FamilyMember::create(['name' => 'عمر', 'gender' => 'male']);
        $child = FamilyMember::create(['name' => 'أحمد', 'gender' => 'male', 'parent_id' => $parent->id]);

        $child->delete();

        $response = $this->getJson('/api/family-tree');
        $tree = $response->json();

        expect($tree[0]['name'])->toBe('عمر');
    });

    test('soft deleted root does not appear in tree', function () {
        $root = FamilyMember::create(['name' => 'عمر', 'gender' => 'male']);

        $root->delete();

        $response = $this->getJson('/api/family-tree');
        $tree = $response->json();

        expect($tree)->toHaveCount(0);
    });
});

describe('Home Page', function () {
    test('home page loads successfully', function () {
        $response = $this->get(route('home'));

        $response->assertOk();
    });
});
