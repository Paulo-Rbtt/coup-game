<?php

namespace App\Console\Commands;

use App\Enums\GamePhase;
use App\Models\Game;
use App\Models\GameResult;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ResetRankingHistory extends Command
{
    protected $signature = 'games:reset-history
                            {--ranking : Reset only the ranking (game_results table)}
                            {--games : Also delete all finished games}
                            {--all : Reset everything: results, finished games, and active games}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Reset game history and ranking data';

    public function handle(): int
    {
        $resetRanking = $this->option('ranking') || $this->option('all') || (!$this->option('games'));
        $resetGames = $this->option('games') || $this->option('all');
        $resetAll = $this->option('all');

        $summary = [];
        if ($resetRanking) $summary[] = 'game_results (ranking)';
        if ($resetGames) $summary[] = 'finished games (game_over)';
        if ($resetAll) $summary[] = 'ALL games (including active/lobby)';

        $this->warn('This will delete: ' . implode(', ', $summary));

        if (!$this->option('force') && !$this->confirm('Are you sure you want to proceed?')) {
            $this->info('Cancelled.');
            return 0;
        }

        $resultsDeleted = 0;
        $gamesDeleted = 0;

        // 1. Delete game results (ranking data)
        if ($resetRanking && Schema::hasTable('game_results')) {
            $resultsDeleted = GameResult::count();
            GameResult::truncate();
            $this->info("✓ Deleted {$resultsDeleted} game results.");
        }

        // 2. Delete finished games
        if ($resetGames) {
            $finishedGames = Game::where('phase', GamePhase::GAME_OVER)->get();
            foreach ($finishedGames as $game) {
                $game->players()->delete();
                $game->delete();
                $gamesDeleted++;
            }
            $this->info("✓ Deleted {$gamesDeleted} finished games.");
        }

        // 3. Delete ALL games (nuclear option)
        if ($resetAll) {
            $remaining = Game::where('phase', '!=', GamePhase::GAME_OVER)->get();
            $allDeleted = 0;
            foreach ($remaining as $game) {
                $game->players()->delete();
                $game->delete();
                $allDeleted++;
            }
            $this->info("✓ Deleted {$allDeleted} additional active/lobby games.");
            $gamesDeleted += $allDeleted;
        }

        $this->newLine();
        $this->info("Reset complete: {$resultsDeleted} results, {$gamesDeleted} games deleted.");

        return 0;
    }
}
