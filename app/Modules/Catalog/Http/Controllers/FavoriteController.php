<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\PlatformProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function toggle(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:platform_product'],
            'id' => ['required', 'integer'],
        ]);

        $model = PlatformProduct::query()
            ->visibleToPublic()
            ->findOrFail($data['id']);
        $class = PlatformProduct::class;

        $existing = Favorite::query()
            ->where('user_id', $request->user()->id)
            ->where('favoritable_type', $class)
            ->where('favoritable_id', $model->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $message = 'Removed from favorites.';
        } else {
            Favorite::create([
                'user_id' => $request->user()->id,
                'favoritable_type' => $class,
                'favoritable_id' => $model->id,
            ]);
            $message = 'Saved to favorites.';
        }

        return back()->with('success', $message);
    }
}
