<?php

namespace App\Http\Controllers;

use App\Models\AgentContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AgentContextController extends Controller
{
    /**
     * Start a new context from an existing one: same project and agent, a fresh
     * claude session, owned by the current user. The chat goes on in it.
     */
    public function store(Request $request, AgentContext $agentContext): RedirectResponse
    {
        $agentContext->startNew($request->user());

        return back()->with('status', 'Új beszélgetés indult.');
    }
}
