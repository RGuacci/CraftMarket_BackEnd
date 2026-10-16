<?php

namespace App\Http\Controllers;

use App\Models\SellerRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SellerRequestController extends Controller
{
    // Candidature
    public function index()
    {
        $sellerRequests = SellerRequest::with('user:id,name,email')
            ->latest()
            ->paginate(12);

        return response()->json($sellerRequests);
    }

    //salvataggio delle richieste
    public function store(Request $request)
    {
        $user = $request->user();

        Gate::authorize('create', SellerRequest::class);

        $hasPendingRequest = $user->sellerRequests()
            ->where('status', 'pending')
            ->exists();

        if ($hasPendingRequest) {
            return response()->json([
                'message' => 'Hai già una richiesta in attesa.',
            ], 409);
        }

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:2000'],
            'category' => ['required', 'string', 'max:100'],
            'motivation' => ['required', 'string', 'max:2000'],
        ]);

        $sellerRequest = $user->sellerRequests()->create($validated);

        return response()->json([
            'message' => 'Richiesta inviata correttamente.',
            'data' => $sellerRequest,
        ], 201);
    }

    // Approvazione di una richiesta
    public function approve(SellerRequest $sellerRequest)
    {
        Gate::authorize('update', $sellerRequest);

        DB::transaction(function () use ($sellerRequest) {
            $sellerRequest->status = 'approved';
            $sellerRequest->save();

            $user = $sellerRequest->user;
            $user->role = 'seller';
            $user->save();
        });

        return response()->json([
            'message' => 'Richiesta approvata.',
        ]);
    }

    //rifiuto di una richiesta
    public function reject(SellerRequest $sellerRequest)
    {
        Gate::authorize('update', $sellerRequest);

        $sellerRequest->status = 'rejected';
        $sellerRequest->save();

        return response()->json([
            'message' => 'Richiesta rifiutata.',
        ]);
    }

    //recupero della candidatura per l'utente
    public function myRequest(Request $request)
    {
        $sellerRequest = $request->user()
            ->sellerRequests()
            ->latest()
            ->first();

        if (! $sellerRequest) {
            return response()->json([
                'message' => 'Non hai ancora inviato una richiesta per diventare seller.',
                'data' => null,
            ]);
        }

        return response()->json([
            'data' => $sellerRequest,
        ]);
    }
}
