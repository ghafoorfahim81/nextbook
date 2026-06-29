<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Services\AttachmentService;

class AttachmentController extends Controller
{
    public function destroy(Attachment $attachment, AttachmentService $attachmentService)
    {
        // The BranchSpecific global scope on Attachment already restricts route-model
        // binding to the current user's branch, so we cannot resolve another branch's row.
        $attachmentService->destroy($attachment);

        return redirect()->back()->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.attachment')]));
    }
}
