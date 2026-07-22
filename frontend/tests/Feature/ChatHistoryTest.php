<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatHistoryTest extends TestCase
{
    public function test_chat_page_renders_new_chat_and_database_history_contract(): void
    {
        Http::fake([
            'http://localhost:8000/api/v1/career/chat' => Http::response([], 200),
            'http://localhost:8000/api/v1/career/chat/threads?limit=20&offset=0' => Http::response([
                'items' => [[
                    'id' => 'thread-1',
                    'title' => 'Backend kariyer planı',
                    'message_count' => 4,
                    'updated_at' => '2026-07-20T20:00:00Z',
                ]],
                'has_more' => false,
            ], 200),
            'http://localhost:8000/*' => Http::response([], 200),
        ]);

        $this->withSession(['panel_locale' => 'tr'])
            ->get('/panel/ai-yardimcisi')
            ->assertOk()
            ->assertSee('Yeni sohbet', false)
            ->assertSee('Sohbet geçmişi', false)
            ->assertSee('Backend kariyer planı', false)
            ->assertSee('data-chat-history-modal', false);
    }

    public function test_new_chat_and_history_detail_are_proxied_to_backend(): void
    {
        Http::fake([
            'http://localhost:8000/api/v1/career/chat/threads' => Http::response([
                'thread' => ['id' => 'thread-new'],
                'archived' => ['id' => 'thread-old', 'title' => 'Eski sohbet', 'message_count' => 2],
            ], 201),
            'http://localhost:8000/api/v1/career/chat/threads/thread-old' => Http::response([
                'thread' => ['id' => 'thread-old', 'title' => 'Eski sohbet', 'message_count' => 2],
                'messages' => [['id' => 'm1', 'role' => 'user', 'content' => 'Merhaba', 'meta' => []]],
            ], 200),
        ]);

        $this->postJson('/panel/ai-yardimcisi/yeni')
            ->assertCreated()
            ->assertJsonPath('archived.id', 'thread-old');
        $this->getJson('/panel/ai-yardimcisi/gecmis/thread-old')
            ->assertOk()
            ->assertJsonPath('messages.0.content', 'Merhaba');

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'http://localhost:8000/api/v1/career/chat/threads');
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && $request->url() === 'http://localhost:8000/api/v1/career/chat/threads/thread-old');
    }
}
