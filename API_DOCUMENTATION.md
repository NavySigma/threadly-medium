# Threadly API Documentation

**Base URL:** `http://localhost:8000/api`

**Auth:** Sanctum token via `Authorization: Bearer {token}` (didapat dari login/register)

## Daftar API

- [A. Auth — Register, Login, Logout](#a-auth-publik)
- [B. User — Profile, Update, Ganti Password](#b-user-profile)
- [C. Follow — Follow, Unfollow, Followers, Following](#c-follow)
- [D. Posts — CRUD, Minimal 15 Point](#d-posts)
- [E. Comments — CRUD, Accept Answer](#e-comments)
- [F. Votes — Upvote, Downvote, Point System](#f-votes)
- [G. Likes / Bookmark — Tambah, Hapus, Daftar](#g-likes--bookmark)
- [H. Search — Global, Posts, Users, Tags](#h-search-publik)
- [I. Categories — List, Detail, Admin CRUD](#i-categories)
- [J. Tags — List, Detail, Admin CRUD](#j-tags)
- [K. Points — History, Recalculate](#k-points)
- [L. Error Codes](#l-error-codes)
- [M. Point System — Ringkasan](#m-point-system--ringkasan)
- [N. Role & Permission](#n-role--permission)

---

## A. AUTH (Publik)

### POST /api/register
Buat akun baru.

**Body:**
```json
{
  "username": "john",
  "email": "john@example.com",
  "password": "secret123",
  "password_confirmation": "secret123"
}
```

**Response:** `201`
```json
{
  "message": "Registrasi berhasil.",
  "data": { "user": {...}, "token": "1|xxx..." }
}
```

> User baru otomatis dapet role `user` dan `reputation_points = 0`.

---

### POST /api/login
Login dan dapet token.

**Body:**
```json
{
  "email": "john@example.com",
  "password": "secret123"
}
```

**Response:** `200` — return user + token

> Login pake email + password_hash.

---

### POST /api/logout 🔐
Hapus token yang dipakai.

**Headers:** `Authorization: Bearer {token}`

**Response:** `200`
```json
{ "message": "Logout berhasil." }
```

---

## B. USER (PROFILE)

### GET /api/users/{user} (Publik)
Lihat profil user lain. **Harus pake UUID**, karena route-model binding default ke kolom `id`.

**Response:** `200`
```json
{
  "data": {
    "id": "uuid",
    "username": "superadmin",
    "avatar_url": null,
    "bio": "...",
    "reputation_points": 10001,
    "created_at": "...",
    "followers_count": 0,
    "following_count": 0,
    "posts_count": 1
  }
}
```

---

### GET /api/users/{user}/posts (Publik)
Lihat postingan milik user tertentu.

**Query:** `?page=1`

**Response:** `200` — paginated posts (open status only)

---

### GET /api/me 🔐
Lihat profile sendiri (lengkap, termasuk email & roles).

**Response:** `200`
```json
{
  "data": {
    "id": "uuid",
    "username": "superadmin",
    "email": "admin@forum.com",
    "avatar_url": null,
    "bio": "...",
    "reputation_points": 10001,
    "created_at": "...",
    "updated_at": "...",
    "roles": [{ "id": "uuid", "name": "admin" }]
  }
}
```

---

### PUT /api/me 🔐
Edit profile sendiri.

**Body:**
```json
{
  "username": "nama_baru",
  "avatar_url": "https://...",
  "bio": "bio baru"
}
```

> Semua field `sometimes` — bisa kirim satu aja.

**Response:** `200`
```json
{ "message": "Profile berhasil diupdate.", "data": {...} }
```

---

### PUT /api/me/password 🔐
Ganti password (revoke semua token lama).

**Body:**
```json
{
  "current_password": "lama",
  "new_password": "baru12345",
  "new_password_confirmation": "baru12345"
}
```

**Response:** `200`
```json
{ "message": "Password berhasil diubah, silakan login ulang." }
```

> Semua token dihapus — user harus login ulang.

---

## C. FOLLOW

### POST /api/users/{user}/follow 🔐
Follow user lain.

> ❌ Tidak bisa follow diri sendiri.
> ❌ Tidak bisa follow kalo udah follow.

**Response:** `201`
```json
{ "message": "Berhasil mengikuti." }
```

---

### DELETE /api/users/{user}/follow 🔐
Unfollow user.

**Response:** `200`
```json
{ "message": "Berhasil unfollow." }
```

---

### GET /api/users/{user}/followers (Publik)
Daftar followers.

**Response:** `200`
```json
{
  "data": [
    { "id": "uuid", "username": "...", "avatar_url": null, "reputation_points": 0 }
  ],
  "meta": { "followers_count": 1 }
}
```

---

### GET /api/users/{user}/following (Publik)
Daftar yang di-follow.

**Response:** `200` — Sama struktur, dengan `meta.following_count`

---

## D. POSTS

### GET /api/posts (Publik)
Daftar post (open only), terbaru dulu.

**Query:** `?search=laravel&category_id=uuid&page=1`

**Response:** `200` — paginated with user, category, tags

---

### GET /api/posts/{post} (Publik)
Detail post. **View count increment otomatis.**

**Response:** `200`
```json
{
  "data": {
    "id": "uuid",
    "title": "...",
    "body": "...",
    "status": "open",
    "view_count": 3,
    "vote_score": 1,
    "is_answered": false,
    "accepted_answer_id": null,
    "user": { "id": "uuid", "username": "...", "avatar_url": null, "reputation_points": 9999 },
    "category": { "id": "uuid", "name": "...", "slug": "..." },
    "tags": [{ "id": "uuid", "name": "Laravel", "slug": "laravel", "color": "#FF2D20" }],
    "accepted_answer": null
  }
}
```

---

### POST /api/posts 🔐
Buat post baru.

> ⚠️ **Minimal 15 reputation points** untuk bisa membuat post.

**Body:**
```json
{
  "category_id": "uuid",
  "title": "Cara belajar Laravel untuk pemula",
  "body": "Halo semua, saya ingin bertanya...",
  "tags": ["uuid_tag1", "uuid_tag2"]
}
```

> `tags` optional, maksimal 5 tag.

**Response:** `201` — return post dengan user, category, tags

---

### PUT /api/posts/{post} 🔐
Edit post. **Hanya owner atau admin.**

> ❌ Post status `closed` tidak bisa diedit (kecuali admin).

**Body:** Sama seperti store, semua `sometimes`.

**Response:** `200`
```json
{ "message": "Post berhasil diupdate.", "data": {...} }
```

---

### DELETE /api/posts/{post} 🔐
Hapus post (soft delete — status jadi `deleted`).

> Bisa oleh **owner**, **moderator**, atau **admin**.

**Response:** `200`
```json
{ "message": "Post berhasil dihapus." }
```

---

## E. COMMENTS

### GET /api/posts/{post}/comments (Publik)
Daftar komentar top-level + replies.

**Response:** `200` — paginated, tiap comment punya `replies` (nested)

> Hati-hati: endpoint ini butuh `PointService` di-resolve. Pastikan service class ada.

---

### POST /api/posts/{post}/comments 🔐
Buat komentar atau reply.

**Body (komentar baru):**
```json
{ "body": "Menurut saya..." }
```

**Body (reply — nested 1 level):**
```json
{ "body": "Setuju!", "parent_id": "uuid_comment" }
```

> ❌ Maksimal 1 level reply (tidak bisa reply of reply).
> ❌ Post status `deleted`/`closed` tidak bisa dikomentari.

**Response:** `201`
```json
{ "message": "Komentar berhasil dibuat.", "data": {...} }
```

---

### PUT /api/comments/{comment} 🔐
Edit komentar. **Hanya owner atau admin.**

**Body:**
```json
{ "body": "Revisi komentar..." }
```

**Response:** `200`

---

### DELETE /api/comments/{comment} 🔐
Hapus komentar. **Hanya moderator/admin** (owner tidak bisa hapus).

> Kalau punya replies, body diganti `[komentar telah dihapus]` (soft).
> Kalau tidak punya replies, dihapus permanen.

**Response:** `200`

---

### POST /api/posts/{post}/comments/{comment}/accept 🔐
Accept komentar sebagai jawaban. **Hanya owner post.**

> ❌ Tidak bisa accept reply (hanya top-level).
> ❌ Comment harus dari post yang sama.
> 🎁 **+10 points** untuk penulis komentar (via PointService).

**Response:** `200`
```json
{ "message": "Jawaban berhasil diterima." }
```

---

### DELETE /api/posts/{post}/unaccept 🔐
Batalkan accept answer. **Hanya owner post.**

**Response:** `200`
```json
{ "message": "Accepted answer berhasil dibatalkan." }
```

---

## F. VOTES

### POST /api/votes 🔐
Vote (upvote/downvote) post atau comment.

**Body:**
```json
{
  "target_type": "post",
  "target_id": "uuid",
  "vote_type": "upvote"
}
```

> `target_type`: `post` atau `comment`
> `vote_type`: `upvote` atau `downvote`

**Rules:**
| Kondisi | Response |
|---|---|
| ✅ Vote baru | `201` — `"Vote berhasil."` |
| ❌ Vote konten sendiri | `422` — `"Tidak bisa vote konten sendiri."` |
| ⚠️ Downvote dengan point < 15 | `422` — `"Minimal 15 poin untuk downvote."` |
| 🔄 Vote type sama (toggle off) | `200` — `"Vote dibatalkan."` |
| 🔄 Vote type beda (ganti) | `200` — `"Vote berhasil diubah."` |

**Point system otomatis via PointService:**
| Aksi | Voter | Owner konten |
|---|---|---|
| Upvote | - | +2 poin |
| Downvote | -1 poin | -2 poin |
| Batal upvote | - | -2 poin |
| Batal downvote | +1 poin | +2 poin |

> Poin minimal adalah **1** (tidak bisa kurang).

---

## G. LIKES / BOOKMARK

### POST /api/likes 🔐
Bookmark post atau comment.

**Body:**
```json
{
  "target_type": "post",
  "target_id": "uuid"
}
```

> ❌ Sudah di-bookmark → `422` `"Sudah di-bookmark."`

**Response:** `201`
```json
{ "message": "Berhasil di-bookmark." }
```

---

### DELETE /api/likes 🔐
Hapus bookmark.

**Body:** Sama seperti like.

**Response:** `200`
```json
{ "message": "Bookmark dihapus." }
```

---

### GET /api/me/bookmarks/posts 🔐
Daftar semua post yang di-bookmark.

**Response:** `200` — paginated, tiap item include target post dengan user, category, tags

---

### GET /api/me/bookmarks/comments 🔐
Daftar semua comment yang di-bookmark.

**Response:** `200` — paginated, include target comment dengan user + judul post

---

## H. SEARCH (Publik)

### GET /api/search?q=keyword
Global search — cari posts, users, tags, categories sekaligus.

**Batasan:** masing-masing maksimal 5 hasil, posts only `status=open`.

**Response:** `200`
```json
{
  "query": "laravel",
  "data": {
    "posts": [...],
    "users": [...],
    "tags": [...],
    "categories": [...]
  }
}
```

---

### GET /api/search/posts?q=keyword
Cari post aja (dengan pagination). Bisa filter:

**Query:** `?q=laravel&category_id=uuid&tag_id=uuid&page=1`

**Response:** `200` — paginated

---

### GET /api/search/users?q=keyword
Cari user aja (dengan pagination).

**Query:** `?q=super&page=1`

**Response:** `200` — paginated, include `posts_count` + `followers_count`

---

### GET /api/search/tags?q=keyword
Cari tag aja (dengan pagination).

**Response:** `200` — paginated, 20 per page

---

## I. CATEGORIES

### GET /api/categories (Publik)
Semua kategori root (parent_id null), include children.

**Response:** `200`
```json
{
  "data": [
    { "id": "uuid", "name": "Web Development", "slug": "web-development", "description": "...", "parent_id": null, "children": [...] }
  ]
}
```

---

### GET /api/categories/{category} (Publik)
Detail kategori + children + 10 post terbaru.

---

### POST /api/categories 🔐 (Admin only)
Buat kategori baru.

**Body:**
```json
{
  "name": "Category Name",
  "description": "...",
  "parent_id": "uuid" // optional, untuk sub-category
}
```

**Response:** `201`

---

### PUT /api/categories/{category} 🔐 (Admin only)
Edit kategori.

> ❌ Tidak bisa set `parent_id` ke diri sendiri.

---

### DELETE /api/categories/{category} 🔐 (Admin only)
Hapus kategori.

> ❌ Tidak bisa hapus kalau masih ada post.
> Children dipindah ke root (`parent_id = null`).

---

## J. TAGS

### GET /api/tags (Publik)
Semua tag, diurutkan berdasarkan `usage_count` (terbanyak dulu).

**Query:** `?search=laravel&page=1`

---

### GET /api/tags/{tag} (Publik)
Detail tag + 10 post terbaru.

---

### POST /api/tags 🔐 (Admin only)
Buat tag baru.

**Body:**
```json
{
  "name": "ReactJS",
  "color": "#61DAFB"
}
```

> `color` optional, format hex `#RRGGBB`.

---

### PUT /api/tags/{tag} 🔐 (Admin only)
Edit tag.

---

### DELETE /api/tags/{tag} 🔐 (Admin only)
Hapus tag (detach dari semua post).

---

## K. POINTS

### GET /api/me/points 🔐
History poin sendiri + summary.

**Query:** `?action_type=upvote&type=earn` (opsional filter)

**Response:** `200`
```json
{
  "summary": {
    "current_points": 10001,
    "total_earned": 2,
    "total_deducted": 0
  },
  "data": { ...paginated points_log... }
}
```

---

### GET /api/users/{userId}/points 🔐 (Admin only)
Lihat history poin user tertentu.

---

### POST /api/users/{userId}/points/recalculate 🔐 (Admin only)
Recalculate `reputation_points` berdasarkan total dari `points_log`.

**Response:** `200`
```json
{ "message": "Points berhasil direcalculate.", "reputation_points": 10001 }
```

---

## L. ERROR CODES

| HTTP Code | Arti |
|---|---|
| 200 | OK |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthenticated (token salah/kadaluarsa) |
| 403 | Forbidden (bukan owner/admin) |
| 404 | Not Found |
| 422 | Validation Error / Business Rule |
| 500 | Internal Server Error |

---

## M. POINT SYSTEM — RINGKASAN

| Aksi | Point | Action Type |
|---|---|---|
| Post di-upvote | +2 (owner) | `content_upvoted` |
| Post di-downvote | -1 (voter), -2 (owner) | `downvote_given`, `content_downvoted` |
| Upvote dibatalkan | -2 (owner) | `upvote_removed` |
| Downvote dibatalkan | +1 (voter), +2 (owner) | `downvote_removed` |
| Jawaban diterima | +10 (penulis) | `answer_accepted` |

> Poin minimal: **1** (tidak bisa negatif).

---

## N. ROLE & PERMISSION

| Role | Kemampuan |
|---|---|
| `user` | CRUD post sendiri, komentar, vote, bookmark, follow |
| `moderator` | User + hapus post/comment siapapun, kelola tag |
| `admin` | Moderator + kelola kategori, kelola roles, recalculate points |

Cek role: `$user->isAdmin()`, `$user->isModerator()`, `$user->isModeratorOrAdmin()`
