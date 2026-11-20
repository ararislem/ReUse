<?php
/**
 * ENVOI D'IMAGES
 * --------------
 * Règles : JPG / PNG / WEBP seulement, 2 Mo maximum.
 * Le vrai type du fichier est lu dans son contenu (finfo), pas dans son nom,
 * et le fichier reçoit un nom aléatoire (on ne garde jamais le nom d'origine).
 */

/**
 * Vérifie et enregistre UNE image envoyée.
 *   $file   : $_FILES['champ']
 *   $folder : UPLOAD_PROFILE_DIR ou UPLOAD_PRODUCT_DIR
 *   $prefix : début du nom de fichier, ex. 'user' ou 'product'
 * Renvoie [nomDuFichier, null] si tout va bien, sinon [null, messageErreur].
 */
function upload_image(array $file, string $folder, string $prefix): array
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($error === UPLOAD_ERR_NO_FILE) {
        return [null, 'Please choose an image.'];
    }
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE || $file['size'] > MAX_UPLOAD_SIZE) {
        return [null, 'The image is too large (max 2 MB).'];
    }
    if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return [null, 'Upload failed, please try again.'];
    }

    // Vrai type MIME lu dans le contenu du fichier
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(ALLOWED_IMAGE_TYPES[$mime]) || @getimagesize($file['tmp_name']) === false) {
        return [null, 'Only JPG, PNG or WEBP images are allowed.'];
    }

    // Nom aléatoire, ex. product_3f9a1c....jpg
    $name = $prefix . '_' . bin2hex(random_bytes(10)) . '.' . ALLOWED_IMAGE_TYPES[$mime];

    if (!is_dir($folder)) {
        mkdir($folder, 0755, true);
    }
    if (!move_uploaded_file($file['tmp_name'], $folder . $name)) {
        return [null, 'Could not save the image on the server.'];
    }
    return [$name, null];
}

/**
 * Transforme un champ <input type="file" name="photos[]" multiple>
 * en une liste de fichiers "normaux" (même format que $_FILES['image']).
 *
 * PHP range les fichiers multiples bizarrement :
 *   $_FILES['photos']['name'][0], $_FILES['photos']['name'][1] ...
 * Cette fonction les remet sous la forme :
 *   [ ['name'=>..., 'tmp_name'=>..., ...], ['name'=>..., ...] ]
 */
function uploaded_files_list(string $field): array
{
    $list = [];
    if (empty($_FILES[$field]['name']) || !is_array($_FILES[$field]['name'])) {
        return $list;
    }
    foreach ($_FILES[$field]['name'] as $i => $name) {
        if ($_FILES[$field]['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;                                   // case vide
        }
        $list[] = [
            'name'     => $name,
            'type'     => $_FILES[$field]['type'][$i],
            'tmp_name' => $_FILES[$field]['tmp_name'][$i],
            'error'    => $_FILES[$field]['error'][$i],
            'size'     => $_FILES[$field]['size'][$i],
        ];
    }
    return $list;
}

/** Supprime un fichier envoyé (basename empêche de sortir du dossier). */
function delete_upload(?string $fileName, string $folder): void
{
    if ($fileName) {
        $path = $folder . basename($fileName);
        if (is_file($path)) {
            unlink($path);
        }
    }
}
