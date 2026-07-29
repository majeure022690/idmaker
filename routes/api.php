<?php

use App\Http\Controllers\Api\GenerationBatchController;
use App\Http\Controllers\Api\IdRecordController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

Route::post('templates/preview', [TemplateController::class, 'preview']);
Route::apiResource('templates', TemplateController::class);
Route::post('uploads', [UploadController::class, 'store']);

Route::get('id-records/ids', [IdRecordController::class, 'ids']);
Route::get('id-records/fields', [IdRecordController::class, 'fields']);
Route::post('id-records/bulk-delete', [IdRecordController::class, 'bulkDestroy']);
Route::get('id-records/trashed', [IdRecordController::class, 'trashed']);
Route::post('id-records/trash/empty', [IdRecordController::class, 'emptyTrash']);
Route::post('id-records/{id}/restore', [IdRecordController::class, 'restore']);
Route::delete('id-records/{id}/force', [IdRecordController::class, 'forceDelete']);
Route::post('id-records/{idRecord}/photo', [IdRecordController::class, 'uploadPhoto']);
Route::post('id-records/{idRecord}/signature', [IdRecordController::class, 'uploadSignature']);
Route::apiResource('id-records', IdRecordController::class);

Route::post('imports', [ImportController::class, 'store']);
Route::post('imports/{importId}/preview', [ImportController::class, 'preview']);
Route::post('imports/{importId}/commit', [ImportController::class, 'commit']);

Route::post('generation-batches/layout-preview', [GenerationBatchController::class, 'layoutPreview']);
Route::post('generation-batches/{generationBatch}/regenerate', [GenerationBatchController::class, 'regenerate']);
Route::apiResource('generation-batches', GenerationBatchController::class)->except(['update']);
