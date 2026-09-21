document.addEventListener('DOMContentLoaded', () => {

    const CARD_SELECTOR = [
        '.overview-card',
        '.dashboard-card',
        '.stat-card',
        '.stats-card',
        '.summary-card',
        '.metric-card'
    ].join(',');

    let lastFocusedCard = null;

    /* =========================================================
       REMOVE OLD GENERATED MODALS
       ========================================================= */

    document
        .querySelectorAll(
            '#pmDashboardCardModal, .pm-card-modal'
        )
        .forEach(element => element.remove());


    /* =========================================================
       CREATE ONE MODAL
       ========================================================= */

    const modal = document.createElement('div');

    modal.id = 'pmDashboardCardModal';

    /*
     * Critical:
     * styles required for positioning are inline so another
     * stylesheet cannot cause the modal to appear at the
     * bottom of the page.
     */
    Object.assign(modal.style, {
        position: 'fixed',
        inset: '0',
        width: '100%',
        height: '100%',
        zIndex: '2147483647',
        display: 'none',
        alignItems: 'center',
        justifyContent: 'center',
        padding: '20px',
        boxSizing: 'border-box'
    });

    modal.setAttribute('aria-hidden', 'true');

    modal.innerHTML = `

        <div
            id="pmDashboardModalBackdrop"
            style="
                position:absolute;
                inset:0;
                background:rgba(15,23,42,.65);
                backdrop-filter:blur(4px);
            "
        ></div>

        <div
            id="pmDashboardModalDialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="pmDashboardModalTitle"
            style="
                position:relative;
                z-index:2;
                width:min(560px,100%);
                max-height:calc(100vh - 40px);
                display:flex;
                flex-direction:column;
                background:#ffffff;
                border:1px solid #e4e7ec;
                border-radius:20px;
                box-shadow:0 30px 90px rgba(0,0,0,.35);
                overflow:hidden;
            "
        >

            <div
                style="
                    padding:20px 22px;
                    display:flex;
                    align-items:flex-start;
                    justify-content:space-between;
                    gap:20px;
                    border-bottom:1px solid #e4e7ec;
                "
            >

                <div>

                    <span
                        style="
                            display:block;
                            margin-bottom:4px;
                            color:#2563eb;
                            font-size:10px;
                            font-weight:800;
                            text-transform:uppercase;
                            letter-spacing:.08em;
                        "
                    >
                        Programme Manager
                    </span>

                    <h2
                        id="pmDashboardModalTitle"
                        style="
                            margin:0;
                            color:#101828;
                            font-size:20px;
                            font-weight:800;
                        "
                    >
                        Dashboard Details
                    </h2>

                </div>

                <button
                    type="button"
                    id="pmDashboardModalClose"
                    aria-label="Close modal"
                    style="
                        width:38px;
                        height:38px;
                        flex:0 0 38px;
                        display:grid;
                        place-items:center;
                        border:1px solid #e4e7ec;
                        border-radius:10px;
                        background:#f8fafc;
                        color:#475467;
                        cursor:pointer;
                    "
                >
                    <i class="fas fa-times"></i>
                </button>

            </div>


            <div
                id="pmDashboardModalBody"
                style="
                    padding:28px 22px;
                    overflow-y:auto;
                "
            ></div>


            <div
                style="
                    padding:16px 22px;
                    display:flex;
                    align-items:center;
                    justify-content:flex-end;
                    gap:10px;
                    border-top:1px solid #e4e7ec;
                "
            >

                <button
                    type="button"
                    id="pmDashboardModalCancel"
                    style="
                        min-height:40px;
                        padding:0 16px;
                        border:1px solid #d0d5dd;
                        border-radius:10px;
                        background:#ffffff;
                        color:#344054;
                        font-size:12px;
                        font-weight:700;
                        cursor:pointer;
                    "
                >
                    Close
                </button>

                <a
                    id="pmDashboardModalDetails"
                    href="#"
                    style="
                        min-height:40px;
                        padding:0 16px;
                        display:none;
                        align-items:center;
                        justify-content:center;
                        gap:8px;
                        border-radius:10px;
                        background:#2563eb;
                        color:#ffffff;
                        text-decoration:none;
                        font-size:12px;
                        font-weight:700;
                    "
                >
                    View Details

                    <i class="fas fa-arrow-right"></i>
                </a>

            </div>

        </div>
    `;

    /*
     * Append directly to body, not dashboard/main/content.
     */
    document.body.appendChild(modal);


    /* =========================================================
       ELEMENTS
       ========================================================= */

    const backdrop =
        document.getElementById(
            'pmDashboardModalBackdrop'
        );

    const dialog =
        document.getElementById(
            'pmDashboardModalDialog'
        );

    const title =
        document.getElementById(
            'pmDashboardModalTitle'
        );

    const body =
        document.getElementById(
            'pmDashboardModalBody'
        );

    const closeButton =
        document.getElementById(
            'pmDashboardModalClose'
        );

    const cancelButton =
        document.getElementById(
            'pmDashboardModalCancel'
        );

    const detailsButton =
        document.getElementById(
            'pmDashboardModalDetails'
        );


    /* =========================================================
       DARK MODE
       ========================================================= */

    function applyTheme() {

        const dark =
            document.documentElement
                .getAttribute('data-theme') === 'dark'
            ||
            document.body
                .classList
                .contains('dark-mode');

        if (dark) {

            dialog.style.background =
                '#1e293b';

            dialog.style.borderColor =
                '#334155';

            title.style.color =
                '#f8fafc';

            closeButton.style.background =
                '#111827';

            closeButton.style.color =
                '#e2e8f0';

            closeButton.style.borderColor =
                '#475569';

            cancelButton.style.background =
                '#111827';

            cancelButton.style.color =
                '#e2e8f0';

            cancelButton.style.borderColor =
                '#475569';

        } else {

            dialog.style.background =
                '#ffffff';

            dialog.style.borderColor =
                '#e4e7ec';

            title.style.color =
                '#101828';

            closeButton.style.background =
                '#f8fafc';

            closeButton.style.color =
                '#475467';

            closeButton.style.borderColor =
                '#e4e7ec';

            cancelButton.style.background =
                '#ffffff';

            cancelButton.style.color =
                '#344054';

            cancelButton.style.borderColor =
                '#d0d5dd';
        }
    }


    /* =========================================================
       GET CARD INFORMATION
       ========================================================= */

    function getText(card, selectors) {

        for (const selector of selectors) {

            const element =
                card.querySelector(selector);

            if (
                element &&
                element.textContent.trim()
            ) {
                return element
                    .textContent
                    .trim();
            }
        }

        return '';
    }


    function getTitle(card) {

        return (
            card.dataset.modalTitle ||

            getText(card, [
                '.overview-card__label',
                '.dashboard-card__title',
                '.stat-card__title',
                '.card-title',
                '.label',
                'h2',
                'h3',
                'h4',
                'strong'
            ]) ||

            'Dashboard Details'
        );
    }


    function getValue(card) {

        return (
            card.dataset.modalValue ||

            getText(card, [
                '.overview-card__value',
                '.dashboard-card__value',
                '.stat-value',
                '.metric-value',
                '.card-value',
                '.value'
            ])
        );
    }


    function getDescription(card) {

        return (
            card.dataset.modalDescription ||

            getText(card, [
                '.overview-card__subtitle',
                '.dashboard-card__subtitle',
                '.card-description',
                '.stat-description',
                '.subtitle',
                'p'
            ])
        );
    }


    function getLink(card) {

        if (card.dataset.modalUrl) {
            return card.dataset.modalUrl;
        }

        const link =
            card.querySelector('a[href]');

        if (!link) {
            return '';
        }

        return link.getAttribute('href') || '';
    }


    /* =========================================================
       BUILD MODAL CONTENT
       ========================================================= */

    function buildContent(card) {

        const cardTitle =
            getTitle(card);

        const cardValue =
            getValue(card);

        const description =
            getDescription(card);

        const link =
            getLink(card);

        title.textContent =
            cardTitle;

        body.innerHTML = '';


        const wrapper =
            document.createElement('div');

        wrapper.style.textAlign =
            'center';


        if (cardValue) {

            const value =
                document.createElement('div');

            value.textContent =
                cardValue;

            Object.assign(
                value.style,
                {
                    marginBottom: '10px',
                    color: '#2563eb',
                    fontSize: '44px',
                    fontWeight: '800',
                    lineHeight: '1'
                }
            );

            wrapper.appendChild(value);
        }


        const heading =
            document.createElement('strong');

        heading.textContent =
            cardTitle;

        Object.assign(
            heading.style,
            {
                display: 'block',
                fontSize: '16px',
                fontWeight: '800'
            }
        );


        const dark =
            document.documentElement
                .getAttribute('data-theme') ===
                'dark';

        heading.style.color =
            dark
                ? '#f8fafc'
                : '#101828';


        wrapper.appendChild(heading);


        if (description) {

            const paragraph =
                document.createElement('p');

            paragraph.textContent =
                description;

            Object.assign(
                paragraph.style,
                {
                    maxWidth: '420px',
                    margin: '10px auto 0',
                    color: dark
                        ? '#94a3b8'
                        : '#667085',
                    fontSize: '13px',
                    lineHeight: '1.6'
                }
            );

            wrapper.appendChild(
                paragraph
            );
        }


        body.appendChild(wrapper);


        /* ---------------------------------------------
           Details button
        --------------------------------------------- */

        if (
            link &&
            link !== '#' &&
            !link
                .toLowerCase()
                .startsWith('javascript:')
        ) {

            detailsButton.href =
                link;

            detailsButton.style.display =
                'inline-flex';

        } else {

            detailsButton.style.display =
                'none';

            detailsButton.removeAttribute(
                'href'
            );
        }
    }


    /* =========================================================
       OPEN
       ========================================================= */

    function openModal(card) {

        lastFocusedCard = card;

        buildContent(card);

        applyTheme();

        /*
         * Force fixed positioning again in case another
         * script has modified the element.
         */

        modal.style.position =
            'fixed';

        modal.style.inset =
            '0';

        modal.style.display =
            'flex';

        modal.style.visibility =
            'visible';

        modal.style.opacity =
            '1';

        modal.style.pointerEvents =
            'auto';


        modal.setAttribute(
            'aria-hidden',
            'false'
        );


        document.body.style.overflow =
            'hidden';


        requestAnimationFrame(() => {
            closeButton.focus();
        });
    }


    /* =========================================================
       CLOSE
       ========================================================= */

    function closeModal() {

        modal.style.display =
            'none';

        modal.style.visibility =
            'hidden';

        modal.style.opacity =
            '0';

        modal.style.pointerEvents =
            'none';


        modal.setAttribute(
            'aria-hidden',
            'true'
        );


        document.body.style.overflow =
            '';


        if (
            lastFocusedCard &&
            typeof lastFocusedCard.focus ===
                'function'
        ) {
            lastFocusedCard.focus();
        }
    }


    /* =========================================================
       PREPARE CARDS
       ========================================================= */

    function prepareCards() {

        document
            .querySelectorAll(
                CARD_SELECTOR
            )
            .forEach(card => {

                if (
                    card.dataset.modal ===
                    'false'
                ) {
                    return;
                }

                card.style.cursor =
                    'pointer';

                if (
                    !card.hasAttribute(
                        'tabindex'
                    )
                ) {
                    card.tabIndex = 0;
                }

                if (
                    !card.hasAttribute(
                        'role'
                    )
                ) {
                    card.setAttribute(
                        'role',
                        'button'
                    );
                }

                card.setAttribute(
                    'aria-haspopup',
                    'dialog'
                );
            });
    }


    prepareCards();


    /* =========================================================
       CARD CLICK
       ========================================================= */

    document.addEventListener(
        'click',
        event => {

            const card =
                event.target.closest(
                    CARD_SELECTOR
                );

            if (!card) {
                return;
            }


            if (
                card.dataset.modal ===
                'false'
            ) {
                return;
            }


            /*
             * Allow form controls inside cards.
             */
            if (
                event.target.closest(
                    'input, select, textarea'
                )
            ) {
                return;
            }


            /*
             * Prevent card link from navigating before
             * modal opens.
             */
            const anchor =
                event.target.closest('a');

            if (
                anchor &&
                card.contains(anchor)
            ) {
                event.preventDefault();
            }


            event.preventDefault();

            event.stopPropagation();

            openModal(card);
        },
        true
    );


    /* =========================================================
       CLOSE EVENTS
       ========================================================= */

    backdrop.addEventListener(
        'click',
        closeModal
    );

    closeButton.addEventListener(
        'click',
        closeModal
    );

    cancelButton.addEventListener(
        'click',
        closeModal
    );


    /* =========================================================
       KEYBOARD
       ========================================================= */

    document.addEventListener(
        'keydown',
        event => {

            if (
                event.key === 'Escape' &&
                modal.style.display ===
                    'flex'
            ) {

                event.preventDefault();

                closeModal();

                return;
            }


            if (
                event.key !== 'Enter' &&
                event.key !== ' '
            ) {
                return;
            }


            const card =
                event.target.closest(
                    CARD_SELECTOR
                );

            if (!card) {
                return;
            }


            event.preventDefault();

            openModal(card);
        }
    );


    /* =========================================================
       WATCH THEME CHANGES
       ========================================================= */

    const observer =
        new MutationObserver(() => {

            if (
                modal.style.display ===
                'flex'
            ) {
                applyTheme();
            }
        });


    observer.observe(
        document.documentElement,
        {
            attributes: true,
            attributeFilter: [
                'data-theme'
            ]
        }
    );

});