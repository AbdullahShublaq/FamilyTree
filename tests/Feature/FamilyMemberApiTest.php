<?php

use App\Models\FamilyMember;

beforeEach(function () {
    $this->grandfather = FamilyMember::create([
        'name' => 'عمر',
        'gender' => 'male',
    ]);
});

describe('GET /api/family-members', function () {
    test('returns all members as json', function () {
        FamilyMember::create([
            'name' => 'أحمد',
            'gender' => 'male',
            'parent_id' => $this->grandfather->id,
        ]);

        $response = $this->getJson('/api/family-members');

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['name' => 'عمر'])
            ->assertJsonFragment(['name' => 'أحمد']);
    });

    test('returns empty array when no members exist', function () {
        FamilyMember::query()->forceDelete();

        $response = $this->getJson('/api/family-members');

        $response->assertOk()
            ->assertJsonCount(0);
    });

    test('each member has required fields', function () {
        $response = $this->getJson('/api/family-members');

        $response->assertOk();

        $member = $response->json()[0];

        expect($member)->toHaveKey('id')
            ->toHaveKey('name')
            ->toHaveKey('gender')
            ->toHaveKey('parent_id')
            ->toHaveKey('birth_date')
            ->toHaveKey('death_date')
            ->toHaveKey('wife_name')
            ->toHaveKey('description');
    });
});

describe('POST /api/family-members', function () {
    test('can create a grandfather with valid data', function () {
        $response = $this->postJson('/api/family-members', [
            'name' => 'محمد',
            'gender' => 'male',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('member.name', 'محمد');

        expect(FamilyMember::where('name', 'محمد')->exists())->toBeTrue();
    });

    test('can create a child with parent_id', function () {
        $response = $this->postJson('/api/family-members', [
            'name' => 'أحمد',
            'gender' => 'male',
            'parent_id' => $this->grandfather->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('member.parent_id', $this->grandfather->id);
    });

    test('can create with all fields', function () {
        $response = $this->postJson('/api/family-members', [
            'name' => 'خالد',
            'gender' => 'male',
            'birth_date' => '1990-05-15',
            'death_date' => '2020-05-15',
            'wife_name' => 'سارة',
            'description' => 'توفي في حادث',
            'parent_id' => $this->grandfather->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('member.wife_name', 'سارة')
            ->assertJsonPath('member.description', 'توفي في حادث');
    });

    test('name is required', function () {
        $response = $this->postJson('/api/family-members', [
            'gender' => 'male',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    test('name must be string', function () {
        $response = $this->postJson('/api/family-members', [
            'name' => 12345,
            'gender' => 'male',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    test('name has max 255 characters', function () {
        $response = $this->postJson('/api/family-members', [
            'name' => str_repeat('ا', 256),
            'gender' => 'male',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    test('gender is required', function () {
        $response = $this->postJson('/api/family-members', [
            'name' => 'محمد',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['gender']);
    });

    test('gender must be male or female', function () {
        $response = $this->postJson('/api/family-members', [
            'name' => 'محمد',
            'gender' => 'other',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['gender']);
    });

    test('gender accepts female', function () {
        $response = $this->postJson('/api/family-members', [
            'name' => 'فاطمة',
            'gender' => 'female',
        ]);

        $response->assertOk()
            ->assertJsonPath('member.gender', 'female');
    });

    test('birth_date must be a valid date', function () {
        $response = $this->postJson('/api/family-members', [
            'name' => 'محمد',
            'gender' => 'male',
            'birth_date' => 'not-a-date',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['birth_date']);
    });

    test('death_date must be after or equal to birth_date', function () {
        $response = $this->postJson('/api/family-members', [
            'name' => 'محمد',
            'gender' => 'male',
            'birth_date' => '2000-01-01',
            'death_date' => '1999-01-01',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['death_date']);
    });

    test('parent_id must exist in family_members table', function () {
        $response = $this->postJson('/api/family-members', [
            'name' => 'محمد',
            'gender' => 'male',
            'parent_id' => 99999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);
    });

    test('parent_id can be null for root member', function () {
        $response = $this->postJson('/api/family-members', [
            'name' => 'محمد',
            'gender' => 'male',
            'parent_id' => null,
        ]);

        $response->assertOk()
            ->assertJsonPath('member.parent_id', null);
    });
});

describe('PUT /api/family-members/{id}', function () {
    test('can update member name', function () {
        $response = $this->putJson("/api/family-members/{$this->grandfather->id}", [
            'name' => 'عمر الجديد',
            'gender' => 'male',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        expect($this->grandfather->fresh()->name)->toBe('عمر الجديد');
    });

    test('can update member gender', function () {
        $response = $this->putJson("/api/family-members/{$this->grandfather->id}", [
            'name' => 'عمر',
            'gender' => 'female',
        ]);

        $response->assertOk();
        expect($this->grandfather->fresh()->gender)->toBe('female');
    });

    test('can update birth_date and death_date', function () {
        $response = $this->putJson("/api/family-members/{$this->grandfather->id}", [
            'name' => 'عمر',
            'gender' => 'male',
            'birth_date' => '1950-01-01',
            'death_date' => '2020-01-01',
        ]);

        $response->assertOk();
        $fresh = $this->grandfather->fresh();
        expect($fresh->birth_date->format('Y-m-d'))->toBe('1950-01-01')
            ->and($fresh->death_date->format('Y-m-d'))->toBe('2020-01-01');
    });

    test('can update wife_name', function () {
        $response = $this->putJson("/api/family-members/{$this->grandfather->id}", [
            'name' => 'عمر',
            'gender' => 'male',
            'wife_name' => 'خديجة',
        ]);

        $response->assertOk();
        expect($this->grandfather->fresh()->wife_name)->toBe('خديجة');
    });

    test('can update description', function () {
        $response = $this->putJson("/api/family-members/{$this->grandfather->id}", [
            'name' => 'عمر',
            'gender' => 'male',
            'description' => 'رجل عظيم',
        ]);

        $response->assertOk();
        expect($this->grandfather->fresh()->description)->toBe('رجل عظيم');
    });

    test('validation fails with invalid data on update', function () {
        $response = $this->putJson("/api/family-members/{$this->grandfather->id}", [
            'gender' => 'male',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    test('returns 404 for non-existent member', function () {
        $response = $this->putJson('/api/family-members/99999', [
            'name' => 'محمد',
            'gender' => 'male',
        ]);

        $response->assertNotFound();
    });
});

describe('DELETE /api/family-members/{id}', function () {
    test('can soft delete a member', function () {
        $response = $this->deleteJson("/api/family-members/{$this->grandfather->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        expect(FamilyMember::find($this->grandfather->id))->toBeNull()
            ->and(FamilyMember::withTrashed()->find($this->grandfather->id))->not->toBeNull();
    });

    test('returns 404 for non-existent member', function () {
        $response = $this->deleteJson('/api/family-members/99999');

        $response->assertNotFound();
    });
});

describe('GET /api/family-tree', function () {
    test('returns nested tree structure', function () {
        $child = FamilyMember::create([
            'name' => 'أحمد',
            'gender' => 'male',
            'parent_id' => $this->grandfather->id,
        ]);

        FamilyMember::create([
            'name' => 'علي',
            'gender' => 'male',
            'parent_id' => $child->id,
        ]);

        $response = $this->getJson('/api/family-tree');

        $response->assertOk();

        $tree = $response->json();

        expect($tree)->toHaveCount(1)
            ->and($tree[0]['name'])->toBe('عمر')
            ->and($tree[0]['children'])->toHaveCount(1)
            ->and($tree[0]['children'][0]['name'])->toBe('أحمد')
            ->and($tree[0]['children'][0]['children'])->toHaveCount(1)
            ->and($tree[0]['children'][0]['children'][0]['name'])->toBe('علي');
    });

    test('returns empty array when no members exist', function () {
        FamilyMember::query()->delete();

        $response = $this->getJson('/api/family-tree');

        $response->assertOk()
            ->assertJsonCount(0);
    });

    test('tree node has required fields', function () {
        $response = $this->getJson('/api/family-tree');

        $node = $response->json()[0];

        expect($node)->toHaveKey('id')
            ->toHaveKey('name')
            ->toHaveKey('gender')
            ->toHaveKey('children');
    });
});
