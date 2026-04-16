<?php

namespace App\Http\Controllers;

use App\Models\FamilyMember;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FamilyMemberController extends Controller
{
    public function index()
    {
        $members = FamilyMember::with(['children', 'parent'])
            ->whereNull('parent_id')
            ->get();

        return Inertia::render('FamilyTree', [
            'members' => $members,
        ]);
    }

    public function getTreeData()
    {
        $members = FamilyMember::with(['children', 'parent'])
            ->whereNull('parent_id')
            ->get();

        return response()->json($this->buildTree($members));
    }

    public function getAllMembers()
    {
        $members = FamilyMember::orderBy('name')->get();

        return response()->json($members);
    }

    private function buildTree($members)
    {
        return $members->map(function ($member) {
            return [
                'id' => $member->id,
                'name' => $member->name,
                'gender' => $member->gender,
                'birth_date' => $member->birth_date?->format('Y-m-d'),
                'death_date' => $member->death_date?->format('Y-m-d'),
                'wife_name' => $member->wife_name,
                'description' => $member->description,
                'children' => $member->children->isNotEmpty()
                    ? $this->buildTree($member->children)
                    : [],
            ];
        });
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'gender' => 'required|in:male,female',
            'birth_date' => 'nullable|date',
            'death_date' => 'nullable|date|after_or_equal:birth_date',
            'wife_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:family_members,id',
        ]);

        $member = FamilyMember::create($validated);

        return response()->json([
            'success' => true,
            'member' => $member->load('parent'),
        ]);
    }

    public function update(Request $request, FamilyMember $familyMember)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'gender' => 'required|in:male,female',
            'birth_date' => 'nullable|date',
            'death_date' => 'nullable|date|after_or_equal:birth_date',
            'wife_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:family_members,id',
        ]);

        $familyMember->update($validated);

        return response()->json([
            'success' => true,
            'member' => $familyMember->load(['parent', 'children']),
        ]);
    }

    public function destroy(FamilyMember $familyMember)
    {
        $this->deleteRecursive($familyMember);

        return response()->json(['success' => true]);
    }

    private function deleteRecursive(FamilyMember $member): void
    {
        $member->children()->each(fn (FamilyMember $child) => $this->deleteRecursive($child));
        $member->delete();
    }
}
