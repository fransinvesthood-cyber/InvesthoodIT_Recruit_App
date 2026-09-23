<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_role('recruiter');

$user        = current_user();
$flashes     = render_flashes();
$currentPage = 'notifications';
$pageTitle   = 'Notifications';

require_once __DIR__ . '/_helpers.php';

$conn = Database::getConnection();

$uid = (int)(
    $user['id']
    ?? $user['user_id']
    ?? 0
);

if ($uid <= 0) {
    http_response_code(403);
    exit('Invalid Recruiter account.');
}

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

$notificationData = rc_notifications(
    $conn,
    $uid,
    100
);

$notifications =
    $notificationData['items']
    ?? [];

$totalNotifications =
    count($notifications);

$unreadNotifications = 0;

foreach ($notifications as $notification) {

    if (
        (int)(
            $notification['is_read']
            ?? 1
        ) === 0
    ) {
        $unreadNotifications++;
    }
}

/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

require __DIR__ . '/_layout_start.php';

?>


<style>

/* ================================================================
   MODAL CONTAINER
================================================================ */

.rc-notifications-modal {
    position: fixed;
    inset: 0;

    z-index: 99999;

    display: flex;
    align-items: center;
    justify-content: center;

    padding: 20px;
}


/* ================================================================
   BACKDROP
================================================================ */

.rc-notifications-modal__backdrop {
    position: absolute;
    inset: 0;

    background:
        rgba(15, 23, 42, .70);

    backdrop-filter: blur(5px);
}


/* ================================================================
   DIALOG
================================================================ */

.rc-notifications-modal__dialog {
    position: relative;
    z-index: 1;

    width: min(680px, 100%);
    max-height: 85vh;

    display: flex;
    flex-direction: column;

    overflow: hidden;

    border-radius: 24px;

    background: #ffffff;

    box-shadow:
        0 30px 90px
        rgba(0, 0, 0, .30);

    animation:
        rcNotificationModalIn
        .20s ease-out;
}


@keyframes rcNotificationModalIn {

    from {
        opacity: 0;

        transform:
            translateY(14px)
            scale(.98);
    }

    to {
        opacity: 1;

        transform:
            translateY(0)
            scale(1);
    }

}


/* ================================================================
   HEADER
================================================================ */

.rc-notifications-modal__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 18px;

    padding:
        22px 24px 18px;

    border-bottom:
        1px solid
        rgba(127, 127, 127, .14);
}


.rc-notifications-modal__heading {
    display: flex;
    align-items: center;

    gap: 13px;
}


.rc-notifications-modal__icon {
    width: 46px;
    height: 46px;

    flex: 0 0 46px;

    display: grid;
    place-items: center;

    border-radius: 14px;

    background:
        rgba(79, 70, 229, .10);

    font-size: 18px;
}


.rc-notifications-modal__heading h3 {
    display: flex;
    align-items: center;

    gap: 7px;

    margin: 0;

    font-size: 20px;
}


.rc-notifications-modal__heading p {
    margin:
        5px 0 0;

    font-size: 13px;

    opacity: .65;
}


/* ================================================================
   COUNT BADGE
================================================================ */

.rc-notifications-count {
    min-width: 21px;
    height: 21px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    padding:
        0 6px;

    border-radius: 999px;

    background: #dc2626;
    color: #ffffff;

    font-size: 10px;
    font-weight: 800;
}


/* ================================================================
   CLOSE BUTTON
================================================================ */

.rc-notifications-modal__close {
    width: 42px;
    height: 42px;

    flex: 0 0 42px;

    display: grid;
    place-items: center;

    border: 0;
    border-radius: 50%;

    background:
        rgba(127, 127, 127, .10);

    color: inherit;

    cursor: pointer;

    transition:
        background .15s ease,
        transform .15s ease;
}


.rc-notifications-modal__close:hover {
    background:
        rgba(127, 127, 127, .18);

    transform: rotate(4deg);
}


/* ================================================================
   TOOLBAR
================================================================ */

.rc-notifications-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 14px;

    padding:
        12px 24px;

    border-bottom:
        1px solid
        rgba(127, 127, 127, .12);
}


.rc-notifications-toolbar__summary {
    font-size: 12px;

    opacity: .7;
}


.rc-notifications-tabs {
    display: flex;

    padding: 3px;

    border-radius: 10px;

    background:
        rgba(127, 127, 127, .08);
}


.rc-notifications-tabs button {
    border: 0;

    padding:
        7px 12px;

    border-radius: 8px;

    background: transparent;

    color: inherit;

    cursor: pointer;

    font-size: 11px;
    font-weight: 700;
}


.rc-notifications-tabs button.is-active {
    background: #ffffff;

    box-shadow:
        0 2px 8px
        rgba(0, 0, 0, .08);
}


/* ================================================================
   BODY
================================================================ */

.rc-notifications-modal__body {
    flex: 1;

    overflow-y: auto;

    overscroll-behavior: contain;
}


/* ================================================================
   NOTIFICATION
================================================================ */

.rc-notification-row {
    position: relative;

    display: flex;
    align-items: flex-start;

    gap: 14px;

    padding:
        17px 24px;

    border-bottom:
        1px solid
        rgba(127, 127, 127, .10);

    transition:
        background .15s ease;
}


.rc-notification-row:last-child {
    border-bottom: 0;
}


.rc-notification-row:hover {
    background:
        rgba(127, 127, 127, .04);
}


.rc-notification-row--unread {
    background:
        rgba(79, 70, 229, .055);
}


.rc-notification-row--unread::before {
    content: '';

    position: absolute;

    left: 7px;
    top: 50%;

    width: 5px;
    height: 5px;

    transform:
        translateY(-50%);

    border-radius: 50%;

    background: #4f46e5;
}


/* ================================================================
   ICON
================================================================ */

.rc-notification-row__icon {
    width: 42px;
    height: 42px;

    flex: 0 0 42px;

    display: grid;
    place-items: center;

    border-radius: 13px;

    background:
        rgba(79, 70, 229, .10);
}


/* ================================================================
   CONTENT
================================================================ */

.rc-notification-row__content {
    flex: 1;

    min-width: 0;
}


.rc-notification-row__title {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 10px;
}


.rc-notification-row__title strong {
    font-size: 14px;
}


.rc-notification-row__message {
    margin:
        5px 0 0;

    font-size: 13px;
    line-height: 1.55;

    opacity: .75;

    word-break: break-word;
}


.rc-notification-row__date {
    display: flex;
    align-items: center;

    gap: 5px;

    margin-top: 8px;

    font-size: 11px;

    opacity: .55;
}


.rc-notification-unread-dot {
    width: 7px;
    height: 7px;

    flex: 0 0 7px;

    border-radius: 50%;

    background: #4f46e5;
}


/* ================================================================
   EMPTY
================================================================ */

.rc-notifications-empty {
    min-height: 300px;

    display: flex;
    flex-direction: column;

    align-items: center;
    justify-content: center;

    padding: 35px;

    text-align: center;
}


.rc-notifications-empty__icon {
    width: 68px;
    height: 68px;

    display: grid;
    place-items: center;

    margin-bottom: 15px;

    border-radius: 21px;

    background:
        rgba(127, 127, 127, .08);

    font-size: 25px;
}


.rc-notifications-empty strong {
    font-size: 16px;
}


.rc-notifications-empty p {
    margin:
        6px 0 0;

    font-size: 13px;

    opacity: .65;
}


/* ================================================================
   FOOTER
================================================================ */

.rc-notifications-modal__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 12px;

    padding:
        14px 24px;

    border-top:
        1px solid
        rgba(127, 127, 127, .13);
}


.rc-notifications-modal__footer span {
    font-size: 11px;

    opacity: .6;
}


/* ================================================================
   DARK MODE
================================================================ */

body.dark-mode
.rc-notifications-modal__dialog,
html[data-theme="dark"]
.rc-notifications-modal__dialog {
    background: #111827;

    color: #f8fafc;
}


body.dark-mode
.rc-notifications-modal__close,
html[data-theme="dark"]
.rc-notifications-modal__close,

body.dark-mode
.rc-notifications-tabs,
html[data-theme="dark"]
.rc-notifications-tabs {
    background:
        rgba(255, 255, 255, .08);
}


body.dark-mode
.rc-notifications-tabs button.is-active,
html[data-theme="dark"]
.rc-notifications-tabs button.is-active {
    background: #1f2937;
}


body.dark-mode
.rc-notification-row:hover,
html[data-theme="dark"]
.rc-notification-row:hover {
    background:
        rgba(255, 255, 255, .035);
}


body.dark-mode
.rc-notification-row--unread,
html[data-theme="dark"]
.rc-notification-row--unread {
    background:
        rgba(99, 102, 241, .10);
}


/* ================================================================
   MOBILE
================================================================ */

@media (max-width: 600px) {

    .rc-notifications-modal {
        align-items: flex-end;

        padding: 0;
    }


    .rc-notifications-modal__dialog {
        width: 100%;
        max-height: 92vh;

        border-radius:
            22px 22px 0 0;
    }


    .rc-notifications-modal__header {
        padding:
            18px 17px 15px;
    }


    .rc-notifications-toolbar {
        padding:
            11px 17px;

        flex-direction: column;
        align-items: flex-start;
    }


    .rc-notification-row {
        padding:
            15px 17px;
    }


    .rc-notifications-modal__footer {
        padding:
            13px 17px;
    }

}

</style>


<!-- ================================================================
     VIEW ALL NOTIFICATIONS MODAL
================================================================ -->

<div
    class="rc-notifications-modal"
    id="recruiterNotificationsModal"
>

    <!-- BACKDROP -->

    <div
        class="rc-notifications-modal__backdrop"
        id="notificationModalBackdrop"
    ></div>


    <!-- DIALOG -->

    <section
        class="rc-notifications-modal__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="recruiterNotificationsTitle"
    >


        <!-- ========================================================
             HEADER
        ========================================================= -->

        <header class="rc-notifications-modal__header">

            <div class="rc-notifications-modal__heading">

                <span class="rc-notifications-modal__icon">

                    <i class="fas fa-bell"></i>

                </span>


                <div>

                    <h3 id="recruiterNotificationsTitle">

                        All Notifications


                        <?php if (
                            $unreadNotifications > 0
                        ): ?>

                            <span class="rc-notifications-count">

                                <?= $unreadNotifications > 99
                                    ? '99+'
                                    : number_format(
                                        $unreadNotifications
                                    ) ?>

                            </span>

                        <?php endif; ?>

                    </h3>


                    <p>
                        Recruiter alerts and account updates.
                    </p>

                </div>

            </div>


            <button
                type="button"
                class="rc-notifications-modal__close"
                id="closeNotificationsModal"
                aria-label="Close notifications"
            >

                <i class="fas fa-xmark"></i>

            </button>

        </header>


        <!-- ========================================================
             TOOLBAR
        ========================================================= -->

        <?php if ($notifications): ?>

            <div class="rc-notifications-toolbar">

                <div class="rc-notifications-toolbar__summary">

                    <strong>
                        <?= number_format(
                            $totalNotifications
                        ) ?>
                    </strong>

                    total

                    ·

                    <strong>
                        <?= number_format(
                            $unreadNotifications
                        ) ?>
                    </strong>

                    unread

                </div>


                <div class="rc-notifications-tabs">

                    <button
                        type="button"
                        class="is-active"
                        data-filter="all"
                    >
                        All
                    </button>


                    <button
                        type="button"
                        data-filter="unread"
                    >
                        Unread
                    </button>

                </div>

            </div>

        <?php endif; ?>


        <!-- ========================================================
             NOTIFICATIONS
        ========================================================= -->

        <div class="rc-notifications-modal__body">

            <?php if (!$notifications): ?>


                <div class="rc-notifications-empty">

                    <span class="rc-notifications-empty__icon">

                        <i class="far fa-bell"></i>

                    </span>


                    <strong>
                        No notifications
                    </strong>


                    <p>
                        New Recruiter notifications will
                        appear here.
                    </p>

                </div>


            <?php else: ?>


                <?php foreach (
                    $notifications
                    as $notification
                ): ?>


                    <?php

                    $isUnread =
                        (int)(
                            $notification['is_read']
                            ?? 1
                        ) === 0;


                    $title =
                        trim(
                            (string)(
                                $notification['title']
                                ?? 'Notification'
                            )
                        );


                    if ($title === '') {
                        $title = 'Notification';
                    }


                    $message =
                        trim(
                            (string)(
                                $notification['message']
                                ?? ''
                            )
                        );


                    $createdAt =
                        $notification['created_at']
                        ?? null;

                    ?>


                    <article
                        class="
                            rc-notification-row
                            <?= $isUnread
                                ? 'rc-notification-row--unread'
                                : '' ?>
                        "
                        data-notification
                        data-unread="<?= $isUnread
                            ? '1'
                            : '0' ?>"
                    >


                        <!-- ICON -->

                        <span class="rc-notification-row__icon">

                            <?php if ($isUnread): ?>

                                <i class="fas fa-bell"></i>

                            <?php else: ?>

                                <i class="far fa-bell"></i>

                            <?php endif; ?>

                        </span>


                        <!-- CONTENT -->

                        <div class="rc-notification-row__content">


                            <div class="rc-notification-row__title">

                                <strong>
                                    <?= e($title) ?>
                                </strong>


                                <?php if ($isUnread): ?>

                                    <span
                                        class="rc-notification-unread-dot"
                                        title="Unread"
                                    ></span>

                                <?php endif; ?>

                            </div>


                            <?php if ($message !== ''): ?>

                                <p class="rc-notification-row__message">

                                    <?= e($message) ?>

                                </p>

                            <?php endif; ?>


                            <?php if ($createdAt): ?>

                                <span class="rc-notification-row__date">

                                    <i class="far fa-clock"></i>

                                    <?= e(
                                        date(
                                            'd M Y, H:i',
                                            strtotime(
                                                $createdAt
                                            )
                                        )
                                    ) ?>

                                </span>

                            <?php endif; ?>


                        </div>

                    </article>


                <?php endforeach; ?>


                <!-- EMPTY UNREAD FILTER -->

                <div
                    class="rc-notifications-empty"
                    id="noUnreadNotifications"
                    hidden
                >

                    <span class="rc-notifications-empty__icon">

                        <i class="fas fa-check"></i>

                    </span>


                    <strong>
                        You're all caught up
                    </strong>


                    <p>
                        There are no unread notifications.
                    </p>

                </div>


            <?php endif; ?>

        </div>


        <!-- ========================================================
             FOOTER
        ========================================================= -->

        <footer class="rc-notifications-modal__footer">

            <span>

                <?= number_format(
                    $totalNotifications
                ) ?>

                <?= $totalNotifications === 1
                    ? 'notification'
                    : 'notifications' ?>

            </span>


            <button
                type="button"
                class="rc-btn rc-btn--secondary"
                id="closeNotificationsModalFooter"
            >

                <i class="fas fa-xmark"></i>

                Close

            </button>

        </footer>


    </section>

</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const modal =
            document.getElementById(
                'recruiterNotificationsModal'
            );

        const closeButton =
            document.getElementById(
                'closeNotificationsModal'
            );

        const footerClose =
            document.getElementById(
                'closeNotificationsModalFooter'
            );

        const backdrop =
            document.getElementById(
                'notificationModalBackdrop'
            );


        /*
        |--------------------------------------------------------------------------
        | Close Modal
        |--------------------------------------------------------------------------
        |
        | notifications.php was opened by the existing notification bell.
        |
        | Closing the modal returns the recruiter to the previous page instead
        | of displaying a separate notifications page.
        |
        */

        function closeModal() {

            if (
                window.history.length > 1
            ) {

                window.history.back();

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Fallback
            |--------------------------------------------------------------------------
            */

            window.location.href =
                <?= json_encode(
                    url(
                        'recruiter/dashboard.php'
                    )
                ) ?>;
        }


        if (closeButton) {

            closeButton.addEventListener(
                'click',
                closeModal
            );

        }


        if (footerClose) {

            footerClose.addEventListener(
                'click',
                closeModal
            );

        }


        if (backdrop) {

            backdrop.addEventListener(
                'click',
                closeModal
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Escape
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key === 'Escape'
                ) {

                    closeModal();

                }

            }
        );


        /*
        |--------------------------------------------------------------------------
        | All / Unread
        |--------------------------------------------------------------------------
        */

        const filterButtons =
            document.querySelectorAll(
                '[data-filter]'
            );

        const notificationRows =
            document.querySelectorAll(
                '[data-notification]'
            );

        const noUnread =
            document.getElementById(
                'noUnreadNotifications'
            );


        filterButtons.forEach(
            function (button) {

                button.addEventListener(
                    'click',
                    function () {

                        const filter =
                            button.dataset.filter;


                        filterButtons.forEach(
                            function (item) {

                                item.classList.remove(
                                    'is-active'
                                );

                            }
                        );


                        button.classList.add(
                            'is-active'
                        );


                        let visibleCount = 0;


                        notificationRows.forEach(
                            function (row) {

                                const unread =
                                    row.dataset.unread
                                    === '1';


                                const visible =
                                    filter === 'all'
                                    ||
                                    (
                                        filter ===
                                            'unread'
                                        &&
                                        unread
                                    );


                                row.hidden =
                                    !visible;


                                if (visible) {
                                    visibleCount++;
                                }

                            }
                        );


                        if (noUnread) {

                            noUnread.hidden =
                                !(
                                    filter ===
                                        'unread'
                                    &&
                                    visibleCount === 0
                                );

                        }

                    }
                );

            }
        );

    }
);

</script>


<?php

require __DIR__ . '/_layout_end.php';

?>