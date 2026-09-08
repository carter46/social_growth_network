<?php

namespace App\Modules\Support\Http\Controllers;

use App\Events\TicketOpened;
use App\Events\TicketReplied;
use App\Http\Controllers\Controller;
use App\Models\SupportAttachment;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Modules\Support\Services\SupportAttachmentService;
use App\Services\Communications\Contact\PlatformContactRepository;
use App\Support\MemberShell;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportTicketController extends Controller
{
    public function __construct(
        private PlatformContactRepository $contact,
        private SupportAttachmentService $attachments,
    ) {}

    public function index(): View
    {
        try {
            $tickets = SupportTicket::query()
                ->where('user_id', auth()->id())
                ->orderByDesc('created_at')
                ->paginate(15);
        } catch (\Throwable $e) {
            report($e);
            $tickets = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15);
        }

        return view('dashboard.user.support.index', [
            'tickets' => $tickets,
            'contact' => $this->contact->all(),
            'layout' => MemberShell::layout(),
            'prefix' => MemberShell::prefix(),
        ]);
    }

    public function create(): View
    {
        return view('dashboard.user.support.create', [
            'categories' => SupportTicket::CATEGORIES,
            'layout' => MemberShell::layout(),
            'prefix' => MemberShell::prefix(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'in:'.implode(',', SupportTicket::CATEGORIES)],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'attachments' => ['nullable', 'array', 'max:'.SupportAttachmentService::MAX_FILES],
            'attachments.*' => ['file', 'max:'.(int) (SupportAttachmentService::MAX_BYTES / 1024)],
        ]);

        $ticket = SupportTicket::create([
            'user_id' => auth()->id(),
            'category' => $validated['category'],
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'status' => 'open',
        ]);

        $this->attachments->storeMany(
            $request->file('attachments'),
            $ticket,
            $request->user()
        );

        TicketOpened::dispatch($ticket->id, (int) auth()->id());

        return redirect()->route(MemberShell::prefix().'.support.show', $ticket)
            ->with('status', __('Support ticket created.'));
    }

    public function show(SupportTicket $ticket): View
    {
        $this->authorize('view', $ticket);

        $ticket->load([
            'replies.user',
            'attachments' => fn ($q) => $q->where('expires_at', '>', now())->orderBy('id'),
        ]);

        return view('dashboard.user.support.show', [
            'ticket' => $ticket,
            'layout' => MemberShell::layout(),
            'prefix' => MemberShell::prefix(),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorize('reply', $ticket);

        $validated = $request->validate([
            'body' => ['required', 'string'],
            'attachments' => ['nullable', 'array', 'max:'.SupportAttachmentService::MAX_FILES],
            'attachments.*' => ['file', 'max:'.(int) (SupportAttachmentService::MAX_BYTES / 1024)],
        ]);

        $isStaff = auth()->user()->hasRole('admin');

        $reply = SupportTicketReply::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'body' => $validated['body'],
            'is_staff' => $isStaff,
        ]);

        $this->attachments->storeMany(
            $request->file('attachments'),
            $ticket,
            $request->user(),
            $reply
        );

        TicketReplied::dispatch($ticket->id, (int) auth()->id(), $isStaff);

        return back()->with('status', __('Reply sent.'));
    }

    public function downloadAttachment(Request $request, SupportAttachment $attachment): StreamedResponse
    {
        if ($attachment->isExpired()) {
            abort(410, __('This evidence file has expired.'));
        }

        $ticket = $attachment->ticket;
        abort_unless($ticket, 404);

        $this->authorize('view', $ticket);

        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404);
        }

        $disposition = $attachment->isImage() ? 'inline' : 'attachment';

        return Storage::disk($attachment->disk)->response(
            $attachment->path,
            $attachment->original_name,
            [
                'Content-Type' => $attachment->mime,
                'Content-Disposition' => $disposition.'; filename="'.$attachment->original_name.'"',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
