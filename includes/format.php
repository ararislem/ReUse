<?php
/**
 * MISE EN FORME (affichage)
 */

/** Prix en dinars : 350 -> "350 DT", 12.5 -> "12.5 DT" */
function format_price($price): string
{
    $price = (float) $price;
    if (floor($price) == $price) {
        return number_format($price, 0, '.', ' ') . ' DT';
    }
    return rtrim(number_format($price, 3, '.', ' '), '0') . ' DT';
}

/** Adresse de la photo de profil (ou avatar par défaut). */
function avatar_url(?string $file): string
{
    if ($file && is_file(UPLOAD_PROFILE_DIR . basename($file))) {
        return url('uploads/profiles/' . rawurlencode(basename($file)));
    }
    return url('assets/images/default-avatar.svg');
}

/** Adresse d'une photo de produit (ou image par défaut). */
function product_image_url(?string $file): string
{
    if ($file && is_file(UPLOAD_PRODUCT_DIR . basename($file))) {
        return url('uploads/products/' . rawurlencode(basename($file)));
    }
    return url('assets/images/placeholder.svg');
}

/** Date relative : "just now", "5 min ago", "3 h ago"... */
function time_ago(string $datetime): string
{
    $seconds = time() - strtotime($datetime);

    if ($seconds < 60)     return 'just now';
    if ($seconds < 3600)   return floor($seconds / 60) . ' min ago';
    if ($seconds < 86400)  return floor($seconds / 3600) . ' h ago';
    if ($seconds < 604800) return floor($seconds / 86400) . ' days ago';
    return date('d M Y', strtotime($datetime));
}

/** Temps restant avant une date : "23 h 15 min" (pour les réservations). */
function time_left(string $datetime): string
{
    $seconds = max(0, strtotime($datetime) - time());
    $hours   = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    return $hours > 0 ? $hours . ' h ' . $minutes . ' min' : $minutes . ' min';
}

/** Petit badge coloré pour l'état du produit. */
function condition_badge(string $condition): string
{
    $classes = [
        'New'      => 'cond-new',
        'Like New' => 'cond-likenew',
        'Good'     => 'cond-good',
        'Used'     => 'cond-used',
    ];
    return '<span class="badge-cond ' . ($classes[$condition] ?? '') . '">' . e($condition) . '</span>';
}
