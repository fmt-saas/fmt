<?php

use core\alert\MessageModel;

$model = MessageModel::create([
        'name'          => 'documents.import.duplicate_document',
        'type'          => 'import',
        'label'         => 'Document already imported',
        'description'   => "This document has already been imported."
    ], 'en')
    ->first();

MessageModel::id($model['id'])->update([
        'label'         => 'Document déjà importé',
        'description'   => "Ce document a déjà été importée.",
    ], 'fr');