<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UnsubscribeController extends Controller
{
    public function __invoke(Request $request, Participant $participant): Response
    {
        abort_unless($request->hasValidSignature(), 403);

        $participant->update(['email_notifications' => false]);

        return response(
            '<html><body style="font-family:sans-serif;text-align:center;padding:60px">'
            .'<h2>Wypisano z przypomnień email</h2>'
            .'<p>Nie będziesz już otrzymywać przypomnień o nadchodzących meczach.</p>'
            .'</body></html>',
            200,
            ['Content-Type' => 'text/html'],
        );
    }
}
