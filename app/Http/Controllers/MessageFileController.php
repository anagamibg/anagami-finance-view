<?php

namespace App\Http\Controllers;

use App\Helper\Files;
use App\Helper\Reply;
use App\Models\UserChat;
use App\Models\UserchatFile;
use Illuminate\Http\Request;

class MessageFileController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.menu.messages';
        $this->middleware(function ($request, $next) {
            abort_403(!in_array('messages', $this->user->modules));
            return $next($request);
        });
    }

    public function store(Request $request)
    {
        if (blank($request->message_id)) {
            return Reply::error(__('messages.fileUploadIssue'));
        }

        if ($request->hasFile('file')) {
            $chat = UserChat::find($request->message_id);

            if (!$chat) {
                return Reply::error(__('messages.fileUploadIssue'));
            }

            // AFV (upgrade 6.0.19): files are attached right after sending, to the sender's own message only
            abort_403($chat->user_one != $this->user->id);

            $companyId = company() ? company()->id : $chat->company_id;

            foreach ($request->file as $fileData) {
                $file = new UserchatFile();
                $file->users_chat_id = $request->message_id;
                $file->company_id = $companyId;

                $filename = Files::uploadLocalOrS3($fileData, UserchatFile::FILE_PATH);

                $file->user_id = $this->user->id;
                $file->filename = $fileData->getClientOriginalName();
                $file->hashname = $filename;
                $file->size = $fileData->getSize();
                $file->save();
            }
        }

        $this->userChatFiles = UserchatFile::where('users_chat_id', $request->message_id)->get();

        $this->chatDetails = UserChat::chatDetail($request->receiver_id, user()->id);
        $messageList = view('messages.message_list', $this->data)->render();

        return Reply::successWithData(__('messages.fileUploaded'), ['message_list' => $messageList]);
    }

    public function destroy(Request $request, $id)
    {
        $file = UserchatFile::findOrFail($id);

        // AFV (upgrade 6.0.19): only the uploader (the UI shows "Delete" only to them)
        abort_403($file->user_id != $this->user->id);

        Files::deleteFile($file->hashname, UserchatFile::FILE_PATH);

        UserchatFile::destroy($id);

        return Reply::success(__('messages.deleteSuccess'));
    }

    /**
     * @param int $id
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\StreamedResponse
     */
    // phpcs:ignore
    public function download($id)
    {
        $file = UserchatFile::whereRaw('md5(id) = ?', $id)->firstOrFail();

        // AFV (upgrade 6.0.19): md5(id) is guessable - only the two members of the conversation
        abort_403(is_null($file->chat) || !in_array($this->user->id, [$file->chat->user_one, $file->chat->user_id]));
        return download_local_s3($file, UserchatFile::FILE_PATH . '/' . $file->hashname);
    }

}
