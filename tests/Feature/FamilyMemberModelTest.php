<?php

use App\Models\FamilyMember;

beforeEach(function () {
    $this->grandfather = FamilyMember::create([
        'name' => 'عمر',
        'gender' => 'male',
    ]);

    $this->child = FamilyMember::create([
        'name' => 'أحمد',
        'gender' => 'male',
        'parent_id' => $this->grandfather->id,
    ]);

    $this->grandchild = FamilyMember::create([
        'name' => 'علي',
        'gender' => 'male',
        'parent_id' => $this->child->id,
    ]);
});

describe('FamilyMember Model', function () {
    test('can create a family member', function () {
        expect($this->grandfather)->toBeInstanceOf(FamilyMember::class)
            ->and($this->grandfather->name)->toBe('عمر')
            ->and($this->grandfather->gender)->toBe('male');
    });

    test('parent_id is nullable for root members', function () {
        expect($this->grandfather->parent_id)->toBeNull();
    });

    test('parent_id is set for child members', function () {
        expect($this->child->parent_id)->toBe($this->grandfather->id);
    });

    test('casts birth_date and death_date as date', function () {
        $member = FamilyMember::create([
            'name' => 'خالد',
            'gender' => 'male',
            'birth_date' => '1990-01-15',
            'death_date' => '2020-06-20',
        ]);

        expect($member->birth_date)->not->toBeNull()
            ->and($member->death_date)->not->toBeNull();
    });

    test('fillable attributes are correct', function () {
        $fillable = (new FamilyMember)->getFillable();

        expect($fillable)->toContain('name')
            ->toContain('gender')
            ->toContain('birth_date')
            ->toContain('death_date')
            ->toContain('wife_name')
            ->toContain('description')
            ->toContain('parent_id');
    });
});

describe('FamilyMember Relationships', function () {
    test('parent relationship returns correct parent', function () {
        expect($this->child->parent->id)->toBe($this->grandfather->id);
    });

    test('children relationship returns correct children', function () {
        $children = $this->grandfather->children;

        expect($children)->toHaveCount(1)
            ->and($children->first()->id)->toBe($this->child->id);
    });

    test('parent of root member returns null', function () {
        expect($this->grandfather->parent)->toBeNull();
    });

    test('grandchild has correct parent chain', function () {
        expect($this->grandchild->parent->id)->toBe($this->child->id)
            ->and($this->grandchild->parent->parent->id)->toBe($this->grandfather->id);
    });

    test('member without children returns empty collection', function () {
        expect($this->grandchild->children)->toHaveCount(0);
    });

    test('member with multiple children returns all', function () {
        FamilyMember::create([
            'name' => 'سعيد',
            'gender' => 'male',
            'parent_id' => $this->grandfather->id,
        ]);

        expect($this->grandfather->fresh()->children)->toHaveCount(2);
    });
});

describe('FamilyMember Accessors', function () {
    test('arabic_gender returns ذكر for male', function () {
        expect($this->grandfather->arabic_gender)->toBe('ذكر');
    });

    test('arabic_gender returns أنثى for female', function () {
        $female = FamilyMember::create([
            'name' => 'فاطمة',
            'gender' => 'female',
        ]);

        expect($female->arabic_gender)->toBe('أنثى');
    });

    test('age returns null when birth_date is null', function () {
        expect($this->grandfather->age)->toBeNull();
    });

    test('age returns correct age from birth_date', function () {
        $member = FamilyMember::create([
            'name' => 'خالد',
            'gender' => 'male',
            'birth_date' => '1990-01-15',
        ]);

        $expectedAge = (int) \Carbon\Carbon::parse('1990-01-15')->diffInYears(now());

        expect($member->age)->toBe($expectedAge);
    });

    test('age calculates from death_date when present', function () {
        $member = FamilyMember::create([
            'name' => 'سالم',
            'gender' => 'male',
            'birth_date' => '1950-03-10',
            'death_date' => '2020-03-10',
        ]);

        expect($member->age)->toBe(70);
    });
});

describe('FamilyMember Soft Deletes', function () {
    test('soft delete marks member as deleted', function () {
        $this->grandfather->delete();

        expect(FamilyMember::find($this->grandfather->id))->toBeNull()
            ->and(FamilyMember::withTrashed()->find($this->grandfather->id))->not->toBeNull()
            ->and(FamilyMember::withTrashed()->find($this->grandfather->id)->deleted_at)->not->toBeNull();
    });

    test('soft deleted member can be restored', function () {
        $this->grandfather->delete();
        $this->grandfather->restore();

        expect($this->grandfather->fresh())->not->toBeNull()
            ->and($this->grandfather->fresh()->deleted_at)->toBeNull();
    });

    test('soft deleted member not in default query', function () {
        $id = $this->grandfather->id;
        $this->grandfather->delete();

        expect(FamilyMember::find($id))->toBeNull();
    });

    test('soft deleted member found with withTrashed', function () {
        $id = $this->grandfather->id;
        $this->grandfather->delete();

        expect(FamilyMember::withTrashed()->find($id))->not->toBeNull();
    });

    test('force delete permanently removes member', function () {
        $id = $this->grandfather->id;
        $this->grandfather->forceDelete();

        expect(FamilyMember::withTrashed()->find($id))->toBeNull();
    });
});
