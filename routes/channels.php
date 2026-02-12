<?php

use Illuminate\Support\Facades\Broadcast;

// Public game channel — no auth needed (uses Channel, not PrivateChannel)
// All game state is broadcast on public channels.
// Private state is sent on player-specific channels keyed by token.
