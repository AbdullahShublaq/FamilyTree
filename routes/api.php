<?php

use App\Http\Controllers\FamilyMemberController;
use Illuminate\Support\Facades\Route;

Route::get('/family-tree', [FamilyMemberController::class, 'getTreeData']);
Route::get('/family-members', [FamilyMemberController::class, 'getAllMembers']);
Route::post('/family-members', [FamilyMemberController::class, 'store']);
Route::put('/family-members/{familyMember}', [FamilyMemberController::class, 'update']);
Route::delete('/family-members/{familyMember}', [FamilyMemberController::class, 'destroy']);
