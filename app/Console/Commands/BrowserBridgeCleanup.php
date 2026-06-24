<?php

namespace App\Console\Commands;

use App\Models\HistoryItem;
use App\Models\TabCommand;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('browserbridge:cleanup')]
#[Description('Remove expired BrowserBridge history items and tab commands.')]
class BrowserBridgeCleanup extends Command
{
    public function handle(): int
    {
        $historyCutoff = now()->subDays((int) config('browserbridge.history_retention_days'));
        $tabCommandCutoff = now()->subDays((int) config('browserbridge.tab_command_retention_days'));

        $deletedHistoryItems = HistoryItem::query()
            ->where('visited_at', '<', $historyCutoff)
            ->delete();

        $deletedTabCommands = TabCommand::query()
            ->where('created_at', '<', $tabCommandCutoff)
            ->delete();

        $this->components->info("Deleted {$deletedHistoryItems} history items and {$deletedTabCommands} tab commands.");

        return self::SUCCESS;
    }
}
