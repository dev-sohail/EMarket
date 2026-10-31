<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class ChatShopController extends Controller
{
    public function index()
    {
        $chatSettings = Setting::getGroup('chat_shop');

        return view('admin.chat-shop.index', compact('chatSettings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'whatsapp_phone' => 'nullable|string|max:20',
            'whatsapp_message' => 'nullable|string|max:500',
            'whatsapp_show_on_site' => 'nullable|boolean',
        ]);

        $fields = [
            'whatsapp_phone' => $validated['whatsapp_phone'] ?? null,
            'whatsapp_message' => $validated['whatsapp_message'] ?? null,
            'whatsapp_show_on_site' => $request->boolean('whatsapp_show_on_site') ? '1' : '0',
        ];

        foreach ($fields as $key => $value) {
            Setting::put($key, $value ?? '', 'chat_shop');
        }

        setting_forget();

        return back()->with('success', 'Chat Shop settings saved successfully.');
    }
}
