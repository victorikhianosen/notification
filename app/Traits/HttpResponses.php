<?php

namespace App\Traits;

trait HttpResponses
{

    public function success(
        string $message = 'Request successful.',
        mixed $data = null
    ) {
        $res = [
            'status' => 'success',
            'message' => $message,
        ];

        if (!is_null($data)) {
            $res['data'] = $data;
        }

        return response()->json($res);
    }

    public function error(
        string $message = 'Request failed.',
    ) {
        $res = [
            'status' => 'error',
            'message' => $message,
        ];

        if (!empty($errors)) {
            $res['errors'] = $errors;
        }

        return response()->json($res);
    }
}
