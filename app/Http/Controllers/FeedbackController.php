<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function index(Request $request)
    {
        $feedbacks = Feedback::query()
            ->when($request->query('rating'), fn ($q, $r) => $q->where('rating', $r))
            ->latest()->paginate(30)->withQueryString();

        return view('feedbacks.index', [
            'feedbacks' => $feedbacks,
            'average' => round((float) Feedback::avg('rating'), 1),
            'count' => Feedback::count(),
        ]);
    }
}
