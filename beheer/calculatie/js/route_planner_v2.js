/**
 * Planner-uitbreiding bovenop de bestaande calculatie-UI:
 * - dag 1 blijft Route 1 + legacy Route 2
 * - extra routes/dagen (S3, XD, RD en daggebonden R1/R2/S3) lopen via route_v2_json
 */
(function () {
    'use strict';

    const BUS_FACTOR_PLANNER = 1.15;
    const ROLLOVER_THRESHOLD_MIN = 180;
    const state = { days: [] };
    let initialized = false;
    let plannerRoot = null;
    let directionsService = null;

    function uid(prefix) {
        return prefix + '_' + Math.random().toString(36).slice(2, 10);
    }

    function parseHm(str) {
        const value = String(str || '').trim();
        const match = value.match(/^(\d{1,2}):(\d{2})$/);
        if (!match) return null;
        const hour = parseInt(match[1], 10);
        const minute = parseInt(match[2], 10);
        if (Number.isNaN(hour) || Number.isNaN(minute)) return null;
        return (hour * 60) + minute;
    }

    function formatHm(totalMinutes) {
        let value = totalMinutes % (24 * 60);
        if (value < 0) value += 24 * 60;
        const hour = Math.floor(value / 60);
        const minute = value % 60;
        return String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
    }

    function addIsoDays(dateStr, days) {
        const value = String(dateStr || '').trim();
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return '';
        const date = new Date(value + 'T12:00:00');
        if (Number.isNaN(date.getTime())) return value;
        date.setDate(date.getDate() + days);
        return date.toISOString().slice(0, 10);
    }

    function getStartDate() {
        return document.getElementById('rit_datum')?.value.trim() || '';
    }

    function readValue(id) {
        const el = document.getElementById(id);
        return el && typeof el.value === 'string' ? el.value.trim() : '';
    }

    function readBaseLastLocation() {
        const route3Day = state.days.find(function (day) {
            return day.date === getStartDate() && day.routes.length > 0;
        });
        if (route3Day) {
            const lastRoute = route3Day.routes.slice().sort(function (a, b) { return a.route_index - b.route_index; }).pop();
            const lastRow = lastRoute && lastRoute.rows.length ? lastRoute.rows[lastRoute.rows.length - 1] : null;
            if (lastRow && lastRow.to) return lastRow.to;
        }
        const route2End = readValue('addr_t_retour_garage') || readValue('addr_t_retour_klant') || readValue('addr_t_vertrek_best');
        if (route2End) return route2End;
        const returnEnd = readValue('addr_t_retour_garage_heen');
        if (returnEnd) return returnEnd;
        const lastVisibleRow = Array.from(document.querySelectorAll('#heen_segmenten_body tr.heen-seg-row')).reverse().find(function (row) {
            return (row.querySelector('.heen-naar')?.value || '').trim() !== '';
        });
        if (lastVisibleRow) {
            return lastVisibleRow.querySelector('.heen-naar')?.value.trim() || '';
        }
        return readValue('addr_t_aankomst_best') || readValue('addr_t_vertrek_klant') || readValue('addr_t_garage');
    }

    function ensureDirectionsService() {
        if (!directionsService && window.google && google.maps) {
            directionsService = new google.maps.DirectionsService();
        }
        return directionsService;
    }

    function routeCode(routeIndex) {
        if (routeIndex === 3) return 'S3';
        return 'R' + routeIndex;
    }

    function routeLabel(routeIndex) {
        return 'Route ' + routeIndex;
    }

    function deepClone(value) {
        return JSON.parse(JSON.stringify(value));
    }

    function buildEmptyRow(prefill) {
        const p = prefill || {};
        return {
            id: uid('row'),
            from: String(p.from || p.van || '').trim(),
            to: String(p.to || p.naar || '').trim(),
            depart_at: String(p.depart_at || p.vertrektijd || '').trim().slice(0, 5),
            arrive_at: String(p.arrive_at || p.aankomst_tijd || '').trim().slice(0, 5),
            depart_day_offset: Math.max(0, parseInt(p.depart_day_offset || 0, 10) || 0),
            arrive_day_offset: Math.max(0, parseInt(p.arrive_day_offset || 0, 10) || 0),
            km: parseFloat(p.km || 0) || 0,
            zone: String(p.zone || 'nl') || 'nl',
            travel_minutes: parseInt(p.travel_minutes || 0, 10) || inferTravelMinutes(p),
            manual_depart: !!(p.manual_depart || p.depart_at || p.vertrektijd)
        };
    }

    function inferTravelMinutes(row) {
        const depart = parseHm(String(row.depart_at || row.vertrektijd || '').trim().slice(0, 5));
        const arrive = parseHm(String(row.arrive_at || row.aankomst_tijd || '').trim().slice(0, 5));
        if (depart === null || arrive === null) return 0;
        let diff = arrive - depart;
        const departOffset = Math.max(0, parseInt(row.depart_day_offset || 0, 10) || 0);
        const arriveOffset = Math.max(0, parseInt(row.arrive_day_offset || 0, 10) || 0);
        diff += (arriveOffset - departOffset) * 1440;
        if (diff < 0) diff += 1440;
        return diff > 0 ? diff : 0;
    }

    function buildEmptyRoute(routeIndex, startLocation, prefillRows) {
        const rows = Array.isArray(prefillRows) && prefillRows.length
            ? prefillRows.map(buildEmptyRow)
            : [buildEmptyRow({ from: startLocation || '' })];
        const route = {
            id: uid('route'),
            route_index: routeIndex,
            code: routeCode(routeIndex),
            label: routeLabel(routeIndex),
            rows: rows
        };
        syncRouteRows(route);
        return route;
    }

    function buildEmptyDay(kind, date) {
        return {
            id: uid('day'),
            date: date || '',
            kind: kind === 'rest' ? 'rest' : 'travel',
            routes: []
        };
    }

    function resolveRelativeMinutes(prevTotal, hm) {
        const minutes = parseHm(hm);
        if (minutes === null) return null;
        if (prevTotal === null) return minutes;
        const baseOffset = Math.floor(prevTotal / 1440) * 1440;
        let candidate = baseOffset + minutes;
        if (candidate < prevTotal - ROLLOVER_THRESHOLD_MIN) {
            candidate += 1440;
        }
        return candidate;
    }

    function syncRouteRows(route) {
        route.rows = route.rows.filter(function (row, idx) {
            return idx === 0 || row.to || row.depart_at || row.arrive_at || row.km > 0;
        });
        if (route.rows.length === 0) {
            route.rows.push(buildEmptyRow({}));
        }
        let previousArrivalTotal = null;
        route.rows.forEach(function (row, idx) {
            if (idx > 0) {
                row.from = route.rows[idx - 1].to || '';
            }
            let departTotal = null;
            if (idx === 0) {
                departTotal = resolveRelativeMinutes(null, row.depart_at);
            } else if (row.manual_depart && row.depart_at) {
                departTotal = resolveRelativeMinutes(previousArrivalTotal, row.depart_at);
            } else if (previousArrivalTotal !== null) {
                departTotal = previousArrivalTotal;
                row.depart_at = formatHm(departTotal);
            } else {
                row.depart_at = row.manual_depart ? row.depart_at : '';
            }
            row.depart_day_offset = departTotal === null ? 0 : Math.floor(departTotal / 1440);
            if (departTotal !== null && row.travel_minutes > 0) {
                const arriveTotal = departTotal + row.travel_minutes;
                row.arrive_at = formatHm(arriveTotal);
                row.arrive_day_offset = Math.floor(arriveTotal / 1440);
                previousArrivalTotal = arriveTotal;
            } else {
                row.arrive_at = '';
                row.arrive_day_offset = row.depart_day_offset || 0;
                previousArrivalTotal = departTotal;
            }
            row.km = parseFloat(row.km || 0) || 0;
            row.zone = row.zone || 'nl';
        });
    }

    function getNextPlannerDate() {
        const currentEnd = document.getElementById('rit_datum_eind')?.value.trim() || getStartDate();
        let maxDate = currentEnd;
        state.days.forEach(function (day) {
            if (day.date && (!maxDate || day.date > maxDate)) {
                maxDate = day.date;
            }
        });
        return maxDate ? addIsoDays(maxDate, 1) : '';
    }

    function ensureDay(date, kind) {
        let day = state.days.find(function (item) { return item.date === date; });
        if (!day) {
            day = buildEmptyDay(kind || 'travel', date);
            state.days.push(day);
            state.days.sort(function (a, b) {
                return String(a.date || '').localeCompare(String(b.date || ''));
            });
        } else if (kind === 'rest') {
            day.kind = 'rest';
            day.routes = [];
        }
        return day;
    }

    function addRouteToDay(day, routeIndex) {
        if (!day || day.kind === 'rest') return;
        if (day.routes.some(function (route) { return route.route_index === routeIndex; })) return;
        const sorted = day.routes.slice().sort(function (a, b) { return a.route_index - b.route_index; });
        const previousRoute = sorted.filter(function (route) { return route.route_index < routeIndex; }).pop();
        const startLocation = previousRoute && previousRoute.rows.length
            ? (previousRoute.rows[previousRoute.rows.length - 1].to || '')
            : (day.date === getStartDate() ? readBaseLastLocation() : findPreviousDayEndLocation(day.date));
        day.routes.push(buildEmptyRoute(routeIndex, startLocation));
        day.routes.sort(function (a, b) { return a.route_index - b.route_index; });
    }

    function findPreviousDayEndLocation(date) {
        const sorted = state.days.slice().sort(function (a, b) {
            return String(a.date || '').localeCompare(String(b.date || ''));
        });
        let last = readBaseLastLocation();
        sorted.forEach(function (day) {
            if (!day.date || day.date >= date) return;
            const route = day.routes.slice().sort(function (a, b) { return a.route_index - b.route_index; }).pop();
            const row = route && route.rows.length ? route.rows[route.rows.length - 1] : null;
            if (row && row.to) last = row.to;
        });
        return last;
    }

    function bootFromRouteV2() {
        const payload = window.ROUTE_V2_BOOT;
        const startDate = getStartDate();
        const bootDays = Array.isArray(payload && payload.days) ? payload.days : [];
        bootDays.forEach(function (bootDay) {
            const date = String(bootDay.date || '').trim();
            const routes = Array.isArray(bootDay.routes) ? bootDay.routes : [];
            const events = Array.isArray(bootDay.events) ? bootDay.events : [];
            const isBaseDay = !!startDate && date === startDate;
            const keepRoutes = routes.filter(function (route) {
                const routeIndex = parseInt(route.route_index || 0, 10) || 0;
                return !(isBaseDay && (routeIndex === 1 || routeIndex === 2));
            });
            const day = keepRoutes.length || events.length || (!isBaseDay && bootDay.kind === 'rest')
                ? ensureDay(date || addIsoDays(startDate, state.days.length), bootDay.kind || 'travel')
                : null;
            if (!day) return;
            if ((bootDay.kind || '') === 'rest') {
                day.kind = 'rest';
            }
            keepRoutes.forEach(function (route) {
                const routeIndex = parseInt(route.route_index || 1, 10) || 1;
                const rows = (route.segments || []).map(function (segment) {
                    return buildEmptyRow({
                        from: segment.from,
                        to: segment.to,
                        depart_at: segment.depart_at,
                        arrive_at: segment.arrive_at,
                        depart_day_offset: segment.depart_day_offset || 0,
                        arrive_day_offset: segment.arrive_day_offset || 0,
                        km: segment.km || 0,
                        zone: segment.zone || 'nl'
                    });
                });
                day.routes.push(buildEmptyRoute(routeIndex, '', rows));
            });
            if (day.routes.length === 0 && events.length) {
                const xdRows = events.filter(function (event) {
                    return String(event.code || '').toUpperCase() === 'XD';
                }).map(function (event) {
                    return buildEmptyRow({
                        from: event.from || '',
                        to: event.to || '',
                        depart_at: event.time || '',
                        km: event.km || 0,
                        zone: event.zone || 'nl'
                    });
                });
                if (xdRows.length) {
                    day.kind = 'travel';
                    day.routes.push(buildEmptyRoute(1, '', xdRows));
                }
            }
            day.routes.sort(function (a, b) { return a.route_index - b.route_index; });
        });

        if (!bootDays.length && window.CALC_TUSSENDAGEN_BOOT && Array.isArray(window.CALC_TUSSENDAGEN_BOOT.items)) {
            window.CALC_TUSSENDAGEN_BOOT.items.forEach(function (item, idx) {
                const day = ensureDay(item.datum || addIsoDays(getStartDate(), idx + 1), 'travel');
                if (!day.routes.length) {
                    day.routes.push(buildEmptyRoute(1, item.van || findPreviousDayEndLocation(day.date), [item]));
                }
            });
        }
    }

    function buildRoutePayload(route) {
        syncRouteRows(route);
        let maxOffset = 0;
        const segments = route.rows.filter(function (row) {
            return row.from || row.to || row.depart_at || row.arrive_at || row.km > 0;
        }).map(function (row, idx) {
            maxOffset = Math.max(maxOffset, row.arrive_day_offset || row.depart_day_offset || 0);
            return {
                seq: idx + 1,
                kind: idx === 0 ? 'garage_to_customer' : 'stop',
                return_kind: '',
                from: row.from || '',
                to: row.to || '',
                depart_at: row.depart_at || '',
                arrive_at: row.arrive_at || '',
                depart_day_offset: row.depart_day_offset || 0,
                arrive_day_offset: row.arrive_day_offset || 0,
                km: parseFloat(row.km || 0) || 0,
                zone: row.zone || 'nl'
            };
        });
        return {
            route_index: route.route_index,
            code: route.code,
            label: route.label,
            mode: 'segment_table',
            enabled: segments.length > 0,
            return_mode: '',
            start_day_offset: segments.length ? (segments[0].depart_day_offset || 0) : 0,
            end_day_offset: maxOffset,
            segments: segments
        };
    }

    function buildPlannerDays(route1Payload, route2Payload, startDate) {
        const days = [];
        const firstDay = {
            seq: 1,
            day_index: 0,
            date: startDate || '',
            kind: 'travel',
            label: 'Dag 1',
            routes: [],
            events: []
        };
        if (route1Payload && Array.isArray(route1Payload.segments) && route1Payload.segments.length) {
            firstDay.routes.push({
                route_index: 1,
                code: 'R1',
                label: route1Payload.label || 'Route 1',
                mode: 'segment_table',
                enabled: true,
                return_mode: route1Payload.return_mode || '',
                start_day_offset: 0,
                end_day_offset: Math.max(0, ...route1Payload.segments.map(function (segment) {
                    return Math.max(parseInt(segment.arrive_day_offset || 0, 10) || 0, parseInt(segment.depart_day_offset || 0, 10) || 0);
                })),
                segments: route1Payload.segments
            });
        }
        if (route2Payload && route2Payload.enabled && Array.isArray(route2Payload.segments) && route2Payload.segments.length) {
            firstDay.routes.push({
                route_index: 2,
                code: 'R2',
                label: 'Route 2',
                mode: 'legacy_route',
                enabled: true,
                start_day_offset: 0,
                end_day_offset: Math.max(0, ...route2Payload.segments.map(function (segment) {
                    return parseInt(segment.time_day_offset || 0, 10) || 0;
                })),
                segments: route2Payload.segments
            });
        }
        firstDay.routes.sort(function (a, b) { return a.route_index - b.route_index; });
        if (firstDay.date || firstDay.routes.length) {
            days.push(firstDay);
        }
        return days;
    }

    function computePlannerEndDate(days, fallbackEndDate) {
        let endDate = fallbackEndDate || '';
        days.forEach(function (day) {
            if (!day || !day.date) return;
            if (!endDate || day.date > endDate) endDate = day.date;
            (day.routes || []).forEach(function (route) {
                const candidate = addIsoDays(day.date, route.end_day_offset || 0);
                if (candidate && (!endDate || candidate > endDate)) {
                    endDate = candidate;
                }
            });
        });
        return endDate;
    }

    function deriveCompatTussendagen(days, startDate) {
        const items = [];
        days.forEach(function (day) {
            if (!day || !day.date || day.date === startDate || day.kind === 'rest') return;
            (day.routes || []).forEach(function (route) {
                (route.segments || []).forEach(function (segment) {
                    items.push({
                        datum: day.date,
                        tijd: segment.depart_at || '',
                        van: segment.from || '',
                        naar: segment.to || '',
                        km: parseFloat(segment.km || 0) || 0,
                        zone: segment.zone || 'nl'
                    });
                });
            });
        });
        return { enabled: items.length > 0, items: items };
    }

    function collectPlannerData(args) {
        const route1 = args.route1 || { label: 'Route 1', segments: [] };
        const route2 = args.route2 || { enabled: false, segments: [] };
        const startDate = args.startDate || getStartDate();
        const days = Array.isArray(args.fallbackDays) && args.fallbackDays.length
            ? deepClone(args.fallbackDays)
            : buildPlannerDays(route1, route2, startDate);
        let firstDay = days.find(function (day) { return day.date === startDate; });
        if (!firstDay) {
            firstDay = {
                seq: 1,
                day_index: 0,
                date: startDate || '',
                kind: 'travel',
                label: 'Dag 1',
                routes: [],
                events: []
            };
            days.unshift(firstDay);
        }
        const sameDayExtras = state.days.filter(function (day) { return day.date === startDate; });
        sameDayExtras.forEach(function (day) {
            day.routes.forEach(function (route) {
                firstDay.routes = firstDay.routes || [];
                firstDay.routes = firstDay.routes.filter(function (existing) {
                    return parseInt(existing.route_index || 0, 10) !== route.route_index;
                });
                firstDay.routes.push(buildRoutePayload(route));
            });
        });
        state.days.filter(function (day) { return day.date !== startDate; }).forEach(function (day) {
            days.push({
                seq: days.length + 1,
                day_index: days.length,
                date: day.date || addIsoDays(startDate, days.length),
                kind: day.kind === 'rest' ? 'rest' : (day.routes.length ? 'travel' : 'extra_drive'),
                label: day.kind === 'rest' ? 'Rustdag' : 'Extra dag',
                routes: day.routes.map(buildRoutePayload),
                events: [{
                    code: day.kind === 'rest' ? 'RD' : 'XD',
                    label: day.kind === 'rest' ? 'Rustdag' : 'Extra dag',
                    date: day.date || addIsoDays(startDate, days.length),
                    time: '',
                    from: '',
                    to: '',
                    km: 0,
                    zone: 'nl'
                }]
            });
        });
        days.sort(function (a, b) {
            return String(a.date || '').localeCompare(String(b.date || ''));
        });
        days.forEach(function (day, index) {
            day.seq = index + 1;
            day.day_index = index;
            day.routes = Array.isArray(day.routes) ? day.routes.slice().sort(function (a, b) {
                return (parseInt(a.route_index || 0, 10) || 0) - (parseInt(b.route_index || 0, 10) || 0);
            }) : [];
        });
        return {
            days: days,
            endDate: computePlannerEndDate(days, args.fallbackEndDate || startDate),
            tussendagen: deriveCompatTussendagen(days, startDate)
        };
    }

    function calcDurationHoursFromRows(rows) {
        const active = rows.filter(function (row) {
            return row.depart_at || row.arrive_at || row.to;
        });
        if (!active.length) return 0;
        const first = active[0];
        const last = active[active.length - 1];
        const start = (first.depart_day_offset || 0) * 1440 + (parseHm(first.depart_at || '') || 0);
        const endBase = parseHm(last.arrive_at || last.depart_at || '') || 0;
        const end = ((last.arrive_at ? last.arrive_day_offset : last.depart_day_offset) || 0) * 1440 + endBase;
        const diff = end - start;
        return diff > 0 ? diff / 60 : 0;
    }

    function calcBaseDayActualHours() {
        let total = 0;
        const route1Start = readValue('time_t_garage');
        const route1End = readValue('time_t_retour_garage_heen') || readValue('time_t_aankomst_best');
        if (route1Start && route1End) {
            let diff = (parseHm(route1End) || 0) - (parseHm(route1Start) || 0);
            if (diff < 0) diff += 1440;
            total += diff / 60;
        }
        const route2Start = readValue('time_t_garage_rit2') || readValue('time_t_vertrek_best');
        const route2End = readValue('time_t_retour_garage');
        if ((readValue('addr_t_vertrek_best') || readValue('time_t_vertrek_best')) && route2Start && route2End) {
            let diff = (parseHm(route2End) || 0) - (parseHm(route2Start) || 0);
            if (diff < 0) diff += 1440;
            total += diff / 60;
        }
        const sameDay = state.days.find(function (day) { return day.date === getStartDate(); });
        if (sameDay) {
            sameDay.routes.forEach(function (route) {
                syncRouteRows(route);
                total += calcDurationHoursFromRows(route.rows);
            });
        }
        return total;
    }

    function hasPlannerExtension() {
        return state.days.some(function (day) {
            return day.routes.length > 0 || day.kind === 'rest';
        });
    }

    function render() {
        if (!plannerRoot) return;
        plannerRoot.innerHTML = '';
        state.days.sort(function (a, b) { return String(a.date || '').localeCompare(String(b.date || '')); });
        state.days.forEach(function (day) {
            const dayCard = document.createElement('div');
            dayCard.className = 'planner-day-card';
            const badge = day.kind === 'rest' ? 'RD' : (day.date === getStartDate() ? 'S3' : 'XD');
            dayCard.innerHTML =
                '<div class="planner-day-head">' +
                    '<div><span class="planner-badge">' + badge + '</span> <strong>' + (day.kind === 'rest' ? 'Rustdag' : (day.date === getStartDate() ? 'Extra routes op dag 1' : 'Extra dag')) + '</strong><div class="planner-day-date">' + (day.date || 'zonder datum') + '</div></div>' +
                    '<div class="planner-day-actions"></div>' +
                '</div>' +
                '<div class="planner-routes"></div>';
            const actionBox = dayCard.querySelector('.planner-day-actions');
            if (day.kind !== 'rest') {
                [1, 2, 3].forEach(function (routeIndex) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'planner-mini-btn';
                    btn.textContent = routeCode(routeIndex);
                    btn.disabled = day.routes.some(function (route) { return route.route_index === routeIndex; });
                    btn.addEventListener('click', function () {
                        addRouteToDay(day, routeIndex);
                        render();
                        notifyPlannerChange();
                    });
                    actionBox.appendChild(btn);
                });
            }
            const removeDayBtn = document.createElement('button');
            removeDayBtn.type = 'button';
            removeDayBtn.className = 'planner-mini-btn planner-mini-btn--danger';
            removeDayBtn.textContent = 'Verwijder';
            removeDayBtn.addEventListener('click', function () {
                state.days = state.days.filter(function (item) { return item.id !== day.id; });
                render();
                notifyPlannerChange();
            });
            actionBox.appendChild(removeDayBtn);

            const routesWrap = dayCard.querySelector('.planner-routes');
            if (day.kind === 'rest') {
                const note = document.createElement('div');
                note.className = 'planner-rest-note';
                note.textContent = 'Rustdag: datum schuift door, geen route-uren of km.';
                routesWrap.appendChild(note);
            }

            day.routes.sort(function (a, b) { return a.route_index - b.route_index; }).forEach(function (route) {
                syncRouteRows(route);
                const routeCard = document.createElement('div');
                routeCard.className = 'planner-route-card';
                routeCard.innerHTML =
                    '<div class="planner-route-head"><strong>' + route.code + ' · ' + route.label + '</strong><div class="planner-route-actions"></div></div>' +
                    '<table class="heen-seg-table planner-seg-table"><thead><tr><th class="heen-td-t">Vertrek</th><th>Van</th><th>Naar</th><th class="heen-td-t">Aankomst</th><th class="heen-zone-col">Zone</th><th class="heen-td-km">Km</th><th class="heen-td-rm"></th></tr></thead><tbody></tbody></table>' +
                    '<button type="button" class="btn-add-bus planner-add-row">+ regel</button>';
                const routeActions = routeCard.querySelector('.planner-route-actions');
                const routeRemove = document.createElement('button');
                routeRemove.type = 'button';
                routeRemove.className = 'planner-mini-btn planner-mini-btn--danger';
                routeRemove.textContent = 'Route weg';
                routeRemove.addEventListener('click', function () {
                    day.routes = day.routes.filter(function (item) { return item.id !== route.id; });
                    if (day.kind !== 'rest' && day.routes.length === 0 && day.date === getStartDate()) {
                        state.days = state.days.filter(function (item) { return item.id !== day.id; });
                    }
                    render();
                    notifyPlannerChange();
                });
                routeActions.appendChild(routeRemove);

                const tbody = routeCard.querySelector('tbody');
                route.rows.forEach(function (row, rowIndex) {
                    const tr = document.createElement('tr');
                    tr.className = 'planner-row';
                    tr.innerHTML =
                        '<td class="heen-td-t"><input type="text" class="form-control custom-time-input planner-vt" placeholder="--:--" readonly></td>' +
                        '<td><input type="text" class="form-control planner-google planner-from" autocomplete="off"></td>' +
                        '<td><input type="text" class="form-control planner-google planner-to" autocomplete="off"></td>' +
                        '<td class="heen-td-t"><input type="text" class="form-control custom-time-input planner-at" placeholder="--:--" readonly></td>' +
                        '<td class="heen-zone-col"><select class="form-control km-zone-select planner-zone"><option value="nl">NL</option><option value="de">DE</option><option value="ch">CH</option><option value="ov">0%</option></select></td>' +
                        '<td class="heen-td-km"><input type="number" class="form-control km-calc planner-km" step="0.1" min="0"></td>' +
                        '<td class="heen-td-rm"><button type="button" class="btn-remove-bus planner-row-remove">&times;</button></td>';
                    const vt = tr.querySelector('.planner-vt');
                    const from = tr.querySelector('.planner-from');
                    const to = tr.querySelector('.planner-to');
                    const at = tr.querySelector('.planner-at');
                    const zone = tr.querySelector('.planner-zone');
                    const km = tr.querySelector('.planner-km');
                    vt.value = row.depart_at || '';
                    from.value = row.from || '';
                    to.value = row.to || '';
                    at.value = row.arrive_at || '';
                    zone.value = row.zone || 'nl';
                    km.value = String(parseFloat(row.km || 0) || 0);
                    if (rowIndex > 0) {
                        from.readOnly = true;
                        from.classList.add('planner-readonly');
                    }
                    vt.addEventListener('click', function (e) {
                        e.preventDefault();
                        if (typeof window.openTimeModal === 'function') {
                            window.__plannerTimeTarget = { dayId: day.id, routeId: route.id, rowId: row.id };
                            window.openTimeModal(vt);
                        }
                    });
                    to.addEventListener('change', function () {
                        row.to = to.value.trim();
                        syncRouteRows(route);
                        render();
                        refreshRouteMetrics(day.id, route.id);
                    });
                    from.addEventListener('change', function () {
                        row.from = from.value.trim();
                        syncRouteRows(route);
                        render();
                        refreshRouteMetrics(day.id, route.id);
                    });
                    zone.addEventListener('change', function () {
                        row.zone = zone.value || 'nl';
                        notifyPlannerChange();
                    });
                    km.addEventListener('change', function () {
                        row.km = parseFloat(km.value || '0') || 0;
                        notifyPlannerChange();
                    });
                    tr.querySelector('.planner-row-remove').addEventListener('click', function () {
                        route.rows = route.rows.filter(function (item) { return item.id !== row.id; });
                        syncRouteRows(route);
                        render();
                        refreshRouteMetrics(day.id, route.id);
                    });
                    tbody.appendChild(tr);
                });
                routeCard.querySelector('.planner-add-row').addEventListener('click', function () {
                    const last = route.rows[route.rows.length - 1];
                    route.rows.push(buildEmptyRow({ from: last ? last.to : '' }));
                    syncRouteRows(route);
                    render();
                    notifyPlannerChange();
                });
                routesWrap.appendChild(routeCard);
            });
            plannerRoot.appendChild(dayCard);
        });
        bindPlannerAutocomplete();
    }

    function bindPlannerAutocomplete() {
        if (!window.google || !google.maps || !google.maps.places) return;
        plannerRoot.querySelectorAll('.planner-google').forEach(function (input) {
            if (input.dataset.acBound === '1') return;
            input.dataset.acBound = '1';
            try {
                const ac = new google.maps.places.Autocomplete(input, { componentRestrictions: { country: ['nl', 'de', 'be', 'at', 'fr'] } });
                ac.addListener('place_changed', function () {
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });
            } catch (e) {}
        });
    }

    function refreshRouteMetrics(dayId, routeId) {
        const service = ensureDirectionsService();
        const day = state.days.find(function (item) { return item.id === dayId; });
        const route = day ? day.routes.find(function (item) { return item.id === routeId; }) : null;
        if (!route) return;
        syncRouteRows(route);
        const activeRows = route.rows.filter(function (row) { return row.to || row.from; });
        if (!service || !activeRows.length || !activeRows[0].from || !activeRows[0].to) {
            route.rows.forEach(function (row) {
                row.travel_minutes = row.travel_minutes || 0;
            });
            syncRouteRows(route);
            render();
            notifyPlannerChange();
            return;
        }
        const destination = activeRows[activeRows.length - 1].to;
        const waypoints = activeRows.slice(0, -1).map(function (row) {
            return { location: row.to, stopover: true };
        });
        service.route({
            origin: activeRows[0].from,
            destination: destination,
            waypoints: waypoints,
            travelMode: 'DRIVING',
            unitSystem: google.maps.UnitSystem.METRIC
        }, function (response, status) {
            if (status !== 'OK' || !response.routes || !response.routes[0]) {
                render();
                notifyPlannerChange();
                return;
            }
            const legs = response.routes[0].legs || [];
            activeRows.forEach(function (row, index) {
                const leg = legs[index];
                if (!leg) return;
                row.km = Math.ceil(leg.distance.value / 1000);
                row.travel_minutes = Math.ceil((leg.duration.value * BUS_FACTOR_PLANNER) / 60);
            });
            syncRouteRows(route);
            render();
            notifyPlannerChange();
        });
    }

    function notifyPlannerChange() {
        if (typeof window.updateRouteV2HiddenInput === 'function') {
            window.updateRouteV2HiddenInput();
        }
        if (typeof window.rekenen === 'function') {
            window.rekenen();
        }
    }

    function bindToolbar() {
        document.getElementById('btn_planner_r2')?.addEventListener('click', function () {
            window.__calcTerugreisUserShow = true;
            if (typeof window.updateVisibility === 'function') window.updateVisibility();
            notifyPlannerChange();
        });
        document.getElementById('btn_planner_s3')?.addEventListener('click', function () {
            const day = ensureDay(getStartDate(), 'travel');
            addRouteToDay(day, 3);
            render();
            notifyPlannerChange();
        });
        document.getElementById('btn_planner_xd')?.addEventListener('click', function () {
            const day = ensureDay(getNextPlannerDate(), 'travel');
            if (!day.routes.length) addRouteToDay(day, 1);
            render();
            notifyPlannerChange();
        });
        document.getElementById('btn_planner_rd')?.addEventListener('click', function () {
            ensureDay(getNextPlannerDate(), 'rest');
            render();
            notifyPlannerChange();
        });
    }

    function hideLegacyExtraDayUi() {
        const extraWrap = document.getElementById('wrap_extra_rijdag');
        if (extraWrap) {
            extraWrap.hidden = true;
            extraWrap.style.display = 'none';
        }
    }

    function init() {
        if (initialized) return;
        plannerRoot = document.getElementById('route_planner_days');
        if (!plannerRoot) return;
        initialized = true;
        hideLegacyExtraDayUi();
        bootFromRouteV2();
        bindToolbar();
        render();
        setTimeout(hideLegacyExtraDayUi, 700);
    }

    window.routePlannerBuildData = function (args) {
        return collectPlannerData(args || {});
    };

    window.routePlannerGetHoursSummary = function (type) {
        if (!hasPlannerExtension()) return null;
        let total = 0;
        const baseHours = calcBaseDayActualHours();
        total += (type === 'meerdaags' || type === 'buitenland') && baseHours > 0 ? Math.max(baseHours, 8) : baseHours;
        state.days.forEach(function (day) {
            if (day.date === getStartDate()) return;
            let actual = 0;
            day.routes.forEach(function (route) {
                syncRouteRows(route);
                actual += calcDurationHoursFromRows(route.rows);
            });
            if (day.kind === 'rest') {
                actual = 0;
            } else if ((type === 'meerdaags' || type === 'buitenland') && (actual > 0 || day.routes.length > 0)) {
                actual = Math.max(actual, 8);
            }
            total += actual;
        });
        return { hours: total };
    };

    window.routePlannerOnTimePicked = function (inputEl) {
        const target = window.__plannerTimeTarget;
        if (!target || !inputEl || !inputEl.classList.contains('planner-vt')) return false;
        const day = state.days.find(function (item) { return item.id === target.dayId; });
        const route = day ? day.routes.find(function (item) { return item.id === target.routeId; }) : null;
        const row = route ? route.rows.find(function (item) { return item.id === target.rowId; }) : null;
        if (!row) return false;
        row.depart_at = inputEl.value.trim().slice(0, 5);
        row.manual_depart = row.depart_at !== '';
        syncRouteRows(route);
        render();
        notifyPlannerChange();
        return true;
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
