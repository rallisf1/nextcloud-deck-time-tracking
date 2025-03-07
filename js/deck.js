(function () {
    // initialize script global variables
    let canManage = false;
    let canTrack = false;
    let userGroups = [];
    let uid = '';
    let ticker = null;
    let baseUrl = '/';
    let oldContent = '';
    let activeCard = 0;
    const tzDiff = new Date().getTimezoneOffset() * -1; // timezone difference in minutes, ready for addition
    const observeTargetNames = ['div.board-wrapper', 'div.smooth-dnd-container.vertical', 'div.smooth-dnd-container.horizontal'];

    // load the script and get the user state
    document.addEventListener('DOMContentLoaded', async function () {
        if(!document.getElementById('toggle-idbadge').checked) {
            console.error(`Check "Show card ID badge" in Deck's settings and refresh to enable time tracking!`);
            return;
        }
        uid = document.getElementsByTagName('head')[0].dataset.user;
        // let's save an XHR request if talk is enabled
        if(document.getElementById('initial-state-spreed-user_group_ids')) {
            userGroups = JSON.parse(atob(document.getElementById('initial-state-spreed-user_group_ids').value));
        } else {
            const groupsRes = await fetch(`/ocs/v1.php/cloud/users/${uid}/groups`, {
                headers: {
                    'Accept': 'application/json',
                    'OCS-APIREQUEST': 'true'
                }
            });
            const groupsJson = await groupsRes.json();
            userGroups = groupsJson.ocs.data.groups;
        }

        baseUrl = document.querySelector('header#header > .header-start > a') ? document.querySelector('header#header > .header-start > a').href : document.querySelector('header#header > .header-left > a').href;
        if(baseUrl.charAt(baseUrl.length - 1) !== '/') baseUrl += '/';

        if(document.querySelector('.modal-mask') || document.querySelector('#app-sidebar-vue')) {
            // card details open on load
            waitForElement('.app-sidebar-tabs', function() {
                canManage = document.querySelector('#app-content-vue').__vue__.$store.getters.canEdit || document.querySelector('#app-content-vue').__vue__.$store.getters.canManage;
                // inject the timesheet tab nav
                document.querySelector('div.app-sidebar-tabs__nav').insertAdjacentHTML('beforeend', '<button data-v-194d90ea="" data-v-d9f30f05="" id="tab-button-timesheet" type="button" aria-controls="tab-timesheet" aria-selected="false" tabindex="-1" role="tab" class="timesheet checkbox-radio-switch app-sidebar-tabs__tab checkbox-radio-switch-button checkbox-radio-switch--button-variant checkbox-radio-switch--button-variant-h-grouped button-vue" style="--icon-size: 24px; --icon-height: 24px;"><!----><span data-v-38a6f3e5="" data-v-194d90ea="" class="checkbox-content checkbox-radio-switch__content checkbox-content-button checkbox-content--button-variant checkbox-content--has-text"><span data-v-38a6f3e5="" aria-hidden="true" inert="inert" class="checkbox-content__icon checkbox-radio-switch__icon"><span data-v-d9f30f05="" aria-hidden="true" role="img" class="material-design-icon timetable-icon" data-v-38a6f3e5=""><svg fill="currentColor" width="20" height="20" viewBox="0 0 24 24" class="material-design-icon__svg"><path fill="currentColor" d="M14 12h1.5v2.82l2.44 1.41l-.75 1.3L14 15.69zM4 2h14a2 2 0 0 1 2 2v6.1c1.24 1.26 2 2.99 2 4.9a7 7 0 0 1-7 7c-1.91 0-3.64-.76-4.9-2H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2m0 13v3h4.67c-.43-.91-.67-1.93-.67-3zm0-7h6V5H4zm14 0V5h-6v3zM4 13h4.29c.34-1.15.97-2.18 1.81-3H4zm11-2.85A4.85 4.85 0 0 0 10.15 15c0 2.68 2.17 4.85 4.85 4.85A4.85 4.85 0 0 0 19.85 15c0-2.68-2.17-4.85-4.85-4.85"/></svg></span></span><span data-v-38a6f3e5="" class="checkbox-content__text checkbox-radio-switch__text"><span data-v-d9f30f05="" data-v-38a6f3e5="" class="app-sidebar-tabs__tab-caption"> Timesheet </span></span></span></button>');
                const urlParts = window.location.href.split('/');
                activeCard = parseInt(urlParts.pop());
                renderDetailsTab();
            })
        }

        const boardObserver = new MutationObserver((records) => {
            const observeTargetNodes = observeTargetNames.flatMap(t => {
                return Array.from(document.querySelectorAll(t))
            });
            // only run for board changes
            if(records.some(r => observeTargetNodes.indexOf(r.target) > -1)) {
                run();
            }
        });

        const detailsObserver = new MutationObserver(async (records) => { // card details
            if((records[0].addedNodes.length && records[0].addedNodes[0].classList.contains("modal-mask") && !document.querySelector('.modal-mask input[name="description"]')) || records[0].addedNodes.length && records[0].addedNodes[0].classList.contains("app-sidebar")) {
                // inject the timesheet tab nav
                document.querySelector('div.app-sidebar-tabs__nav').insertAdjacentHTML('beforeend', '<button data-v-194d90ea="" data-v-d9f30f05="" id="tab-button-timesheet" type="button" aria-controls="tab-timesheet" aria-selected="false" tabindex="-1" role="tab" class="timesheet checkbox-radio-switch app-sidebar-tabs__tab checkbox-radio-switch-button checkbox-radio-switch--button-variant checkbox-radio-switch--button-variant-h-grouped button-vue" style="--icon-size: 24px; --icon-height: 24px;"><!----><span data-v-38a6f3e5="" data-v-194d90ea="" class="checkbox-content checkbox-radio-switch__content checkbox-content-button checkbox-content--button-variant checkbox-content--has-text"><span data-v-38a6f3e5="" aria-hidden="true" inert="inert" class="checkbox-content__icon checkbox-radio-switch__icon"><span data-v-d9f30f05="" aria-hidden="true" role="img" class="material-design-icon timetable-icon" data-v-38a6f3e5=""><svg fill="currentColor" width="20" height="20" viewBox="0 0 24 24" class="material-design-icon__svg"><path fill="currentColor" d="M14 12h1.5v2.82l2.44 1.41l-.75 1.3L14 15.69zM4 2h14a2 2 0 0 1 2 2v6.1c1.24 1.26 2 2.99 2 4.9a7 7 0 0 1-7 7c-1.91 0-3.64-.76-4.9-2H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2m0 13v3h4.67c-.43-.91-.67-1.93-.67-3zm0-7h6V5H4zm14 0V5h-6v3zM4 13h4.29c.34-1.15.97-2.18 1.81-3H4zm11-2.85A4.85 4.85 0 0 0 10.15 15c0 2.68 2.17 4.85 4.85 4.85A4.85 4.85 0 0 0 19.85 15c0-2.68-2.17-4.85-4.85-4.85"/></svg></span></span><span data-v-38a6f3e5="" class="checkbox-content__text checkbox-radio-switch__text"><span data-v-d9f30f05="" data-v-38a6f3e5="" class="app-sidebar-tabs__tab-caption"> Timesheet </span></span></span></button>');
                await new Promise(r => setTimeout(r, 100)); // this is bad, but activeCard is updated after the MutationObserver callback...
                renderDetailsTab();
            }
        });
        let target = document.querySelector('label[for="toggle-modal"]').previousElementSibling.checked ? 'body' : '#app-content-vue + div';
        document.querySelector('label[for="toggle-modal"]').previousElementSibling.addEventListener("click", () => {
            target = (this.checked) ? 'body' : '#app-content-vue + div';
            detailsObserver.disconnect();
            detailsObserver.observe(document.querySelector(target), { childList: true, subtree: false, attributes: false, characterData: false });
        });
        boardObserver.observe(document.querySelector('#app-content-vue'), { childList: true, subtree: true });
        detailsObserver.observe(document.querySelector(target), { childList: true, subtree: false, attributes: false, characterData: false });
    });
    // some helper functions
    // a simple (character encoding agnostic) string hasher
    function fnv1aHash(str) {
        let hash = 0x811c9dc5; // FNV offset basis
        for (let i = 0; i < str.length; i++) {
            hash ^= str.charCodeAt(i);
            hash += (hash << 1) + (hash << 4) + (hash << 7) + (hash << 8) + (hash << 24);
        }
        return (hash >>> 0).toString(16); // Convert to unsigned hex
    }
    // get days hours minutes seconds off a timestamp
    function formatTitleTime(seconds) {
        seconds = parseInt(seconds);
        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        return `${days}d ${hours}h ${minutes}m ${secs}s`;
    }
    // get hours minutes off a timestamp
    function formatInnerTime(seconds) {
        seconds = parseInt(seconds);
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
    }
    function renderDetailsTab() {
        const card = document.querySelector('#app-content-vue').__vue__.$store.state.card.cards.find(c => c.id === activeCard);
        // inject the timesheet tab content
        document.querySelector('div.app-sidebar-tabs__content').insertAdjacentHTML('beforeend', `<section data-v-095ea4ce="" id="tab-timesheet" aria-hidden="true" aria-labelledby="tab-button-timesheet" tabindex="0" role="tabpanel" class="app-sidebar__tab timesheet" data-v-d9f30f05=""><h3 data-v-095ea4ce="" class="hidden-visually"> Timesheet </h3> <div data-v-0d4a7a97="" data-v-268a1d16="" class="timesheet table-wrapper" data-v-095ea4ce="">${renderTable(card.timesheets)}</div></section>`);
        // add the nav event listener
        document.querySelector('div.app-sidebar-tabs__nav').addEventListener('click', function (event) {
            let tabButton;
            if(event.target.tagName.toLowerCase() === 'button') {
                tabButton = event.target;
            } else {
                tabButton = event.target.closest('button.checkbox-radio-switch');
            }
            if(tabButton.id === 'tab-button-timesheet') {
                if(tabButton.classList.contains('active')) return; // already active
                // de-activate the active tab nav
                document.querySelector('div.app-sidebar-tabs__nav .checkbox-radio-switch--checked').setAttribute("aria-selected", "false");
                document.querySelector('div.app-sidebar-tabs__nav .checkbox-radio-switch--checked').classList.remove('checkbox-radio-switch--checked', 'active');
                document.querySelector('div.app-sidebar-tabs__nav .checkbox-content__icon--checked').classList.remove('checkbox-content__icon--checked');
                // de-activate the active tab content
                document.querySelector('div.app-sidebar-tabs__content .app-sidebar__tab:not([aria-hidden])').classList.remove('app-sidebar__tab--active');
                document.querySelector('div.app-sidebar-tabs__content .app-sidebar__tab:not([aria-hidden])').setAttribute("aria-hidden", "true");
                // activate the timesheet tab nav
                document.querySelector('div.app-sidebar-tabs__nav button#tab-button-timesheet').classList.add('checkbox-radio-switch--checked', 'active');
                document.querySelector('div.app-sidebar-tabs__nav button#tab-button-timesheet').setAttribute("aria-selected", "true");
                document.querySelector('div.app-sidebar-tabs__nav button#tab-button-timesheet .checkbox-content__icon').classList.add('checkbox-content__icon--checked');
                // activate the timesheet tab content
                document.querySelector('div.app-sidebar-tabs__content .app-sidebar__tab#tab-timesheet').classList.add('app-sidebar__tab--active');
                document.querySelector('div.app-sidebar-tabs__content .app-sidebar__tab#tab-timesheet').removeAttribute("aria-hidden");
            } else {
                if(document.querySelector('div.app-sidebar-tabs__nav button#tab-button-timesheet.active')) {
                    // de-activate timesheet tab
                    document.querySelector('div.app-sidebar-tabs__nav button#tab-button-timesheet').classList.remove('checkbox-radio-switch--checked', 'active');
                    document.querySelector('div.app-sidebar-tabs__nav button#tab-button-timesheet').setAttribute("aria-selected", "false");
                    document.querySelector('div.app-sidebar-tabs__nav button#tab-button-timesheet .checkbox-content__icon').classList.remove('checkbox-content__icon--checked');
                    document.querySelector('div.app-sidebar-tabs__content .app-sidebar__tab#tab-timesheet').classList.remove('app-sidebar__tab--active');
                    document.querySelector('div.app-sidebar-tabs__content .app-sidebar__tab#tab-timesheet').setAttribute("aria-hidden", "true");
                    // activate clicked tab
                    tabButton.classList.add('checkbox-radio-switch--checked', 'active');
                    tabButton.setAttribute("aria-selected", "true");
                    tabButton.querySelector('.checkbox-content__icon').classList.add('checkbox-content__icon--checked');
                    document.querySelector(`div.app-sidebar-tabs__content .app-sidebar__tab#${tabButton.id.replace('-button', '')}`).classList.add('app-sidebar__tab--active');
                    document.querySelector(`div.app-sidebar-tabs__content .app-sidebar__tab#${tabButton.id.replace('-button', '')}`).removeAttribute("aria-hidden");
                }
            }
        });
        // add the buttons event listeners
        addTableEventListeners(card);
    }
    // get date in local format and in user's timezone from the API's UTC ISO format
    function formatTableTime(date) {
        const datePartTypes = datepickerFormatDate.toLowerCase().split(/\/|-/);
        const separator = datepickerFormatDate.indexOf('-') ? '-' : '/';
        const parsedDate = new Date(date.replace(" ", "T").split('.')[0]);
        parsedDate.setTime(parsedDate.getTime() + tzDiff * 60000);
        const dateArray = [];
        datePartTypes.forEach(dpt => {
            switch(dpt) {
                case 'd':
                    dateArray.push(parsedDate.getDate().toString().padStart(2, '0'));
                    break;
                case 'm':
                    dateArray.push((parsedDate.getMonth() + 1).toString().padStart(2, '0'));
                    break;
                default:
                    // let's assume everything else is for year
                    dateArray.push(parsedDate.getFullYear());
            }
        });
        return `${dateArray.join(separator)} ${parsedDate.getHours().toString().padStart(2, '0')}:${parsedDate.getMinutes().toString().padStart(2, '0')}:${parsedDate.getSeconds().toString().padStart(2, '0')}`;
    }
    // convert mysql datetime to timestamp, or get current
    function datetimeToUnix(datetime = null) {
        if(!datetime) return Math.floor(new Date().getTime() / 1000);
        return Math.floor(new Date(datetime.replace(" ", "T")).getTime() / 1000);
    }
    // needed for card details urls
    function waitForElement(selector, callback) {
        const interval = setInterval(() => {
            const element = document.querySelector(selector);
            if (element) {
                clearInterval(interval);
                callback(element);
            }
        }, 100); // Check every 100ms
    }
    // update the card timers
    function updateTicker() {
        if(ticker && typeof ticker.clearInterval === "function") ticker.clearInterval();
        ticker = setInterval(function () {
            document.querySelectorAll('button.timesheet.tracker.active').forEach(b => {
                b.dataset.track = parseInt(b.dataset.track) + 1;
                b.textContent = `${formatInnerTime(b.dataset.track)} ■`;
                b.title = `${formatTitleTime(b.dataset.track)} | Stop Timer`;
            })
        }, 1000);
    }
    // render table edit buttons
    function renderTable(timesheets = []) {
        // build the timesheet table
        let table = '<table class="timesheet"><thead><tr><th>Assignee</th><th>Start</th><th>End</th><th>Duration</th><th>Description</th><th align="center">';
        if(canManage) table += '<button title="Add Timesheet" class="add"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M13.72 21.84c-.56.1-1.13.16-1.72.16c-5.5 0-10-4.5-10-10S6.5 2 12 2s10 4.5 10 10c0 .59-.06 1.16-.16 1.72A5.9 5.9 0 0 0 19 13c-1.26 0-2.43.39-3.4 1.06l-3.1-1.86V7H11v6l3.43 2.11A5.96 5.96 0 0 0 13 19c0 1.03.26 2 .72 2.84M18 15v3h-3v2h3v3h2v-3h3v-2h-3v-3z"/></svg></button>';
        table += '</th></tr></thead><tbody>';
        const workers = new Set([]);
        let totalDuration = 0;
        if(!timesheets.length) {
            table += `<tr><td colspan="6">No timesheets found for this card</td></tr>`;
        }
        timesheets.forEach(t => {
            workers.add(t.user_id);
            const end_date = t.end ? formatTableTime(t.end.date) : '';
            const start_ts = datetimeToUnix(t.start.date.split('.')[0]);
            const end_ts = t.end ? datetimeToUnix(t.end.date.split('.')[0]) : datetimeToUnix() - tzDiff * 60;
            const duration_ts = end_ts - start_ts;
            totalDuration += duration_ts;
            const canEdit = canManage || uid === t.user_id;
            table += `<tr><td>${t.user_id}</td><td>${formatTableTime(t.start.date)}</td><td>${end_date}</td><td>${formatTitleTime(duration_ts)}</td><td style="white-space: pre-wrap">${t.description}</td><td align="center">`;
            if(canEdit) table += `<button class="edit" title="Edit Timesheet" data-id="${t.id}"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M21 13.1c-.1 0-.3.1-.4.2l-1 1l2.1 2.1l1-1c.2-.2.2-.6 0-.8l-1.3-1.3c-.1-.1-.2-.2-.4-.2m-1.9 1.8l-6.1 6V23h2.1l6.1-6.1zm-8.1 7c-5.1-.5-9-4.8-9-9.9C2 6.5 6.5 2 12 2c5.3 0 9.6 4.1 10 9.3c-.3-.1-.6-.2-1-.2c-.8 0-1.4.4-1.8.8l-2.7 2.7l-4-2.4V7H11v6l4.4 2.7l-4.4 4.4z"/></svg></button> <button class="delete" title="Delete Timesheet" data-id="${t.id}"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M13.72 21.84c-.56.1-1.13.16-1.72.16c-5.5 0-10-4.5-10-10S6.5 2 12 2s10 4.5 10 10c0 .59-.06 1.16-.16 1.72A5.9 5.9 0 0 0 19 13c-1.26 0-2.43.39-3.4 1.06l-3.1-1.86V7H11v6l3.43 2.11A5.96 5.96 0 0 0 13 19c0 1.03.26 2 .72 2.84m7.4-6.38L19 17.59l-2.12-2.12l-1.41 1.41L17.59 19l-2.12 2.12l1.41 1.42L19 20.41l2.12 2.13l1.42-1.42L20.41 19l2.13-2.12z"/></svg></button>`;
            table += '</td></tr>';
        })
        table += `</tbody><tfoot><td colspan="3">${workers.size} people worked on this</td><td colspan="3">for a total of ${formatTitleTime(totalDuration)}</td></tfoot></table>`;
        return table;
    }

    function addTableEventListeners(card) {
        if(document.querySelector('table.timesheet button.add')) {
            document.querySelector('table.timesheet button.add').addEventListener("click", function () {
                OCDialogs.confirmHtml(timesheetForm(card.assignedUsers, card.id), 'Add a Timesheet record', async (ok) => {
                    if(ok) {
                        const data = new FormData(document.querySelector('form[name="timesheet-form"]'));
                        const startDate = new Date(datetimeToUnix(`${data.get('start')}:00`) * 1000);
                        const endDate = data.get('end').length ? new Date(datetimeToUnix(`${data.get('end')}:00`) * 1000) : null;
                        const response = await fetch(`${baseUrl}apps/decktimetracking/timesheet`, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'OCS-APIREQUEST': 'true'
                            },
                            body: JSON.stringify({
                                cardId: data.get('card_id'),
                                userId: data.get('assignee'),
                                start: startDate.toISOString(),
                                end: endDate ? endDate.toISOString() : null,
                                description: data.get('description')
                            })
                        });
                
                        if (response.ok) {
                            const t = await response.json();
                            card.timesheets.push(t);
                            document.querySelector('div.timesheet.table-wrapper').innerHTML = renderTable(card.timesheets);
                            addTableEventListeners(card);
                        }
                    }
                });
            });
        }
        document.querySelectorAll('table.timesheet button.delete').forEach(b => b.addEventListener("click", function (event) {
            const timesheetId = event.currentTarget.dataset.id;
            OCDialogs.confirmDestructive(
                "Are you sure you want to delete this entry?",
                "Delete Timesheet Record",
                70,
                (ok) => {
                    if(ok) {
                        fetch(`${baseUrl}apps/decktimetracking/timesheet/${timesheetId}`, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'OCS-APIREQUEST': 'true'
                            }
                        }).then(function(response) {
                            if (response.ok) {
                                card.timesheets.splice(card.timesheets.findIndex(o => o.id == timesheetId), 1);
                                document.querySelector('div.timesheet.table-wrapper').innerHTML = renderTable(card.timesheets);
                                addTableEventListeners(card);
                            }
                        });
                    }
                }
        )}));
        document.querySelectorAll('table.timesheet button.edit').forEach(b => b.addEventListener("click", function (event) {
            const timesheetId = event.currentTarget.dataset.id;
            OCDialogs.confirmHtml(timesheetForm(card.assignedUsers, card.id, card.timesheets.find(t => t.id == timesheetId)), 'Edit Timesheet record', async (ok) => {
                if(ok) {
                    const data = new FormData(document.querySelector('form[name="timesheet-form"]'));
                    const startDate = new Date(datetimeToUnix(`${data.get('start')}:00`) * 1000);
                    const endDate = data.get('end').length ? new Date(datetimeToUnix(`${data.get('end')}:00`) * 1000) : null;
                    const response = await fetch(`${baseUrl}apps/decktimetracking/timesheet/${timesheetId}`, {
                        method: 'PUT',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'OCS-APIREQUEST': 'true'
                        },
                        body: JSON.stringify({
                            userId: data.get('assignee'),
                            start: startDate.toISOString(),
                            end: endDate ? endDate.toISOString() : null,
                            description: data.get('description')
                        })
                    });
            
                    if (response.ok) {
                        const t = await response.json();
                        card.timesheets[card.timesheets.findIndex(o => o.id == t.id)] = t;
                        document.querySelector('div.timesheet.table-wrapper').innerHTML = renderTable(card.timesheets);
                        addTableEventListeners(card);
                    }
                }
            });
        }));
    }

    // runner
    function run() {
        if(!document.querySelector('div.board > div.smooth-dnd-container') || !document.querySelector('div.board > div.smooth-dnd-container').childElementCount) return; // board is still loading
        const newContent = fnv1aHash(document.querySelector('div.board > div.smooth-dnd-container').innerHTML);
        if(oldContent === newContent) return; // nothing changed
        console.log('timetracker script running');
        oldContent = newContent;

        canManage = document.querySelector('#app-content-vue').__vue__.$store.getters.canEdit || document.querySelector('#app-content-vue').__vue__.$store.getters.canManage;
        canTrack = false;

        document.querySelectorAll('.card').forEach((cardEl) => {
            cardEl.addEventListener("click", function (event) {
                activeCard = parseInt(event.currentTarget.querySelector('.inline-badges > .cardid').textContent.replace('#', ''));
            });
            const cardId = parseInt(cardEl.querySelector('.inline-badges > .cardid').textContent.replace('#', ''));
            document.querySelector('#app-content-vue').__vue__.$store.state.card.cards.some(c => {
                if(cardId === c.id && !c.archived && !c.done) {
                    c.assignedUsers.some(ca => {
                        if(ca.type === 0) { // user
                            if(ca.participant.uid === uid) {
                                canTrack = true;
                                return true;
                            }
                        }
                        if(ca.type === 1) { // group
                            if(userGroups.indexOf(ca.participant.uid) > -1) {
                                canTrack = true;
                                return true;
                            }
                        }
                    })
                    const ownActiveTimer = c.timesheets.find(t => t.user_id === uid && !t.end);
                    const otherActiveTimer = c.timesheets.find(t => t.user_id !== uid && !t.end);
                    injectButton(cardEl, c.id, ownActiveTimer, otherActiveTimer);
                    return true;
                }
            });
        });
        updateTicker();
    }
    // button maker
    function injectButton(cardEl, cardId, ownActiveTimer, otherActiveTimer) {
        if (!cardId) return;
        // remove any existing button as permissions or assignees might have changed
        if(cardEl.querySelector('button.timesheet')) cardEl.querySelector('button.timesheet').remove();

        const button = document.createElement('button');
        button.classList.add('timesheet', 'tracker');
        button.dataset.card = cardId;
        button.textContent = '';

        if(otherActiveTimer && canManage && !canTrack) {
            // for managers to see ongoing tasks
            button.textContent = '⧗';
            button.classList.add('ongoing');
            button.title = "Someone is working on it";
        } else if(canTrack) {
            // for assignees
            if(ownActiveTimer){
                button.classList.add('active');
                button.dataset.id = ownActiveTimer.id;
                button.dataset.track = datetimeToUnix() - (datetimeToUnix(ownActiveTimer.start.date.split('.')[0]) + tzDiff * 60)
                button.textContent = `${formatInnerTime(button.dataset.track)} ■`;
                button.title = `${formatTitleTime(button.dataset.track)} | Stop Timer`;
            } else {
                button.textContent = '▶';
                button.classList.add('inactive');
                button.title = 'Start Timer';
            }
        }
        if(button.textContent !== ''){
            button.addEventListener('click', async function (event) {
                if(button.classList.contains('active')) {
                    event.stopPropagation();
                    await stopTracking(button);
                    updateTicker();
                }
                if(button.classList.contains('inactive')) {
                    event.stopPropagation();
                    await startTracking(button);
                    updateTicker();
                }
            });
            cardEl.appendChild(button);
        }
    }

    async function startTracking(button) {
        const cardId = button.dataset.card;
        const response = await fetch(`${baseUrl}apps/decktimetracking/card/${cardId}`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'OCS-APIREQUEST': 'true'
            }
        });

        if (response.ok) {
            const timesheet = await response.json()
            button.classList.remove('inactive');
            button.classList.add('active');
            button.dataset.id = timesheet.id;
            button.textContent = `00:01 ■`;
            button.dataset.track = 1;
            button.title = `${formatTitleTime(button.dataset.track)} | Stop Timer`;
        }
    }

    function stopTracking(button) {
        const timesheetId = button.dataset.id;
        // I know OC.Dialogs is deprecated but it was the easiest way to have a pretty dialog
        OCDialogs.prompt(
            "What did you do?",
            "Timesheet Description",
            (ok,value) => {
                let desc = ''
                if(ok) desc = value;
                fetch(`${baseUrl}apps/decktimetracking/card/${timesheetId}`, {
                    method: 'PUT',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'OCS-APIREQUEST': 'true'
                    },
                    body: JSON.stringify({
                        description: desc
                    })
                }).then(function(response) {
                    if (response.ok) {
                        button.textContent = '▶';
                        button.classList.remove('active');
                        button.classList.add('inactive');
                        button.dataset.id = null;
                    }
                });
            },
            true,
            "description",
        false);
        /* without deprecated OC.Dialogs
        const desc = prompt("What did you do?");
        const response = await fetch(`${baseUrl}apps/decktimetracking/card/${timesheetId}`, {
            method: 'PUT',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'OCS-APIREQUEST': 'true'
            },
            body: JSON.stringify({
                description: desc
            })
        });

        if (response.ok) {
            button.textContent = '▶';
            button.classList.remove('active');
            button.classList.add('inactive');
            button.dataset.id = null;
        }
        */
    }

    function timesheetForm(assignees, card_id, timesheet = null) {
        const form = document.createElement("form");
        form.name = 'timesheet-form';
        form.className = 'timesheet';
    
        // Hidden ID input
        const idInput = document.createElement("input");
        idInput.name = 'id';
        idInput.type = 'hidden';
        idInput.setAttribute('value', timesheet !== null ? timesheet.id : '');
        form.appendChild(idInput);
    
        // Hidden Card ID input
        const cardIdInput = document.createElement("input");
        cardIdInput.name = 'card_id';
        cardIdInput.type = 'hidden';
        cardIdInput.setAttribute('value', card_id);
        form.appendChild(cardIdInput);
        
        if(!canManage) {
            // Hidden User ID input
            const userIdInput = document.createElement("input");
            userIdInput.name = 'assignee';
            userIdInput.type = 'hidden';
            userIdInput.setAttribute('value', timesheet !== null ? timesheet.id : uid);
            form.appendChild(userIdInput);
        } else {
            // User select dropdown
            const userDiv = document.createElement("div");
            const userLabel = document.createElement("label");
            userLabel.textContent = 'Assignee:';
            userLabel.htmlFor = 'assignee';
            const usersInput = document.createElement("select");
            usersInput.name = 'assignee';
            usersInput.id = 'assignee';
            usersInput.required = true;
            assignees.forEach(a => {
                if(a.type === 0) {
                    usersInput.add(new Option(a.participant.displayname, a.participant.uid));
                }
            });
            if(timesheet !== null) {
                for (const option of usersInput.options) {
                    if (timesheet.user_id === option.value) {
                        option.setAttribute('selected', 'selected');
                        break;
                    }
                }
            }
            userDiv.appendChild(userLabel);
            userDiv.appendChild(usersInput);
            form.appendChild(userDiv);
        }
    
        // Start date input
        const startDiv = document.createElement("div");
        const startLabel = document.createElement("label");
        startLabel.textContent = 'Start Time:';
        startLabel.htmlFor = 'start';
        const startInput = document.createElement("input");
        startInput.name = 'start';
        startInput.id = 'start';
        startInput.type = 'datetime-local';
        startInput.setAttribute('value', '');
    
        // End date input
        const endDiv = document.createElement("div");
        const endLabel = document.createElement("label");
        endLabel.textContent = 'End Time:';
        endLabel.htmlFor = 'end';
        const endInput = document.createElement("input");
        endInput.name = 'end';
        endInput.id = 'end';
        endInput.type = 'datetime-local';
        endInput.setAttribute('value', '');
    
        if(timesheet !== null) {
            // my brain is not braining but I need to add tzDiff twice (60000 * 2) for the times to match
            const startDate = new Date(datetimeToUnix(timesheet.start.date.split('.')[0]) * 1000 + tzDiff * 120000);
            startInput.setAttribute('value', startDate.toISOString().slice(0, 16));
            if(timesheet.end !== null) {
                const endDate = new Date(datetimeToUnix(timesheet.end.date.split('.')[0]) * 1000 + tzDiff * 120000);
                endInput.setAttribute('value', endDate.toISOString().slice(0, 16));
            }
        }
    
        startDiv.appendChild(startLabel);
        startDiv.appendChild(startInput);
        form.appendChild(startDiv);
    
        endDiv.appendChild(endLabel);
        endDiv.appendChild(endInput);
        form.appendChild(endDiv);
    
        // Description textarea
        const descDiv = document.createElement("div");
        const descLabel = document.createElement("label");
        descLabel.textContent = 'Description:';
        descLabel.htmlFor = 'description';
        const descriptionInput = document.createElement("textarea");
        descriptionInput.name = 'description';
        descriptionInput.id = 'description';
        descriptionInput.textContent = timesheet !== null ? timesheet.description : '';
        descDiv.appendChild(descLabel);
        descDiv.appendChild(descriptionInput);
        form.appendChild(descDiv);
    
        return form.outerHTML;
    }
    

})();
