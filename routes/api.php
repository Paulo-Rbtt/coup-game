<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\GameActionController;
use App\Http\Controllers\LobbyController;
use App\Http\Controllers\RankingController;
use Illuminate\Support\Facades\Route;

// ── Lobby ────────────────────────────────────────────
Route::post('/games', [LobbyController::class, 'create']);
Route::post('/games/join', [LobbyController::class, 'join']);
Route::post('/games/reconnect', [LobbyController::class, 'reconnect']);
Route::get('/rooms', [LobbyController::class, 'listRooms']);
Route::post('/games/{game}/start', [LobbyController::class, 'start']);
Route::post('/games/{game}/toggle-ready', [LobbyController::class, 'toggleReady']);
Route::post('/games/{game}/leave-lobby', [LobbyController::class, 'leaveLobby']);
Route::post('/games/{game}/kick', [LobbyController::class, 'kick']);
Route::post('/games/{game}/rematch', [LobbyController::class, 'rematch']);
Route::get('/games/{game}/state', [LobbyController::class, 'state']);

// ── Game Actions ─────────────────────────────────────
Route::post('/games/{game}/action', [GameActionController::class, 'declareAction']);
Route::post('/games/{game}/pass', [GameActionController::class, 'pass']);
Route::post('/games/{game}/challenge', [GameActionController::class, 'challengeAction']);
Route::post('/games/{game}/block', [GameActionController::class, 'declareBlock']);
Route::post('/games/{game}/challenge-block', [GameActionController::class, 'challengeBlock']);
Route::post('/games/{game}/lose-influence', [GameActionController::class, 'loseInfluence']);
Route::post('/games/{game}/exchange', [GameActionController::class, 'exchange']);
Route::post('/games/{game}/leave', [GameActionController::class, 'leave']);

// ── Chat ─────────────────────────────────────────────
Route::post('/games/{game}/chat', [ChatController::class, 'send']);

// ── Rankings & History ───────────────────────────────
Route::get('/rankings', [RankingController::class, 'index']);
Route::get('/history', [RankingController::class, 'history']);
Route::get('/games/{game}/results', [RankingController::class, 'gameResults']);
