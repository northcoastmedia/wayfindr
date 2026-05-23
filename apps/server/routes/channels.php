<?php

use App\Broadcasting\ConversationChannel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('conversations.{supportCode}', ConversationChannel::class);
