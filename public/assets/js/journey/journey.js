/* ==========================================================================
   Ancestral Journey - progressive enhancement
   - cinematic generation reveal
   - interactive pan/zoom + person detail on the SVG tree
   Everything degrades gracefully: without this file both pages still render.
   ========================================================================== */
(function () {
    'use strict';

    var LABELS = {
        0: 'Focus person',
        1: 'Parents',
        2: 'Grandparents',
        3: 'Great-grandparents',
        4: '2x great-grandparents',
        5: '3x great-grandparents'
    };
    var SHORT_LABELS = {
        0: 'Focus',
        1: 'Parents',
        2: 'Grandparents',
        3: 'Great-grandparents',
        4: '2x great',
        5: '3x great'
    };
    var CERT_LABELS = {
        verified: 'Verified',
        proposed: 'Proposed',
        unknown: 'Unknown',
        disputed: 'Disputed'
    };

    function readPayload() {
        var el = document.getElementById('journey-payload');
        if (!el) {
            return null;
        }
        try {
            return JSON.parse(el.textContent || el.innerText || '{}');
        } catch (error) {
            return null;
        }
    }

    function generationLabel(level) {
        return LABELS[level] || (level + ' generations back');
    }

    function shortGenerationLabel(level) {
        return SHORT_LABELS[level] || (level + ' back');
    }

    function yearsText(node) {
        var birth = node.birth_year !== null && node.birth_year !== undefined ? String(node.birth_year) : '?';
        if (node.living) {
            return birth + ' - living';
        }
        if (node.death_year !== null && node.death_year !== undefined) {
            return birth + ' - ' + node.death_year;
        }
        return birth === '?' ? 'dates unknown' : 'born ' + birth;
    }

    function placeText(node) {
        var places = [];
        if (node.birth_place) {
            places.push(node.birth_place);
        }
        if (node.death_place) {
            places.push(node.death_place);
        }
        return places.length ? places.join(' / ') : 'Not recorded';
    }

    function statusLabel(certainty) {
        return CERT_LABELS[certainty] || 'Unknown';
    }

    function setText(el, value) {
        if (el) {
            el.textContent = value === null || value === undefined ? '' : String(value);
        }
    }

    /* ------------------------------------------------------------- animate */
    function initAnimate(payload) {
        var root = document.querySelector('[data-journey-animate]');
        var stage = root ? root.querySelector('[data-journey-stage]') : null;
        if (!root || !stage) {
            return;
        }

        var nodes = {};
        (payload.nodes || []).forEach(function (node) {
            nodes[node.id] = node;
        });

        var order = [];
        var labelByLevel = {};
        (payload.generations || []).forEach(function (generation) {
            labelByLevel[generation.level] = generation.label;
            (generation.ids || []).forEach(function (id) {
                order.push({ id: id, level: generation.level });
            });
        });

        // Signal that the interactive version is ready; CSS hides the static list.
        root.classList.add('is-enhanced');

        if (!order.length) {
            return;
        }

        var card = stage.querySelector('[data-journey-card]');
        var kicker = stage.querySelector('[data-journey-kicker]');
        var nameEl = stage.querySelector('[data-journey-name]');
        var yearsEl = stage.querySelector('[data-journey-years]');
        var recordEl = stage.querySelector('[data-journey-record]');
        var generationEl = stage.querySelector('[data-journey-generation]');
        var branchEl = stage.querySelector('[data-journey-branch]');
        var placeEl = stage.querySelector('[data-journey-place]');
        var noteEl = stage.querySelector('[data-journey-note]');
        var progress = stage.querySelector('[data-journey-progress]');
        var progressBar = stage.querySelector('[data-journey-progressbar]');
        var levelEl = stage.querySelector('[data-journey-level]');
        var trail = stage.querySelector('[data-journey-trail]');
        var prevBtn = stage.querySelector('[data-journey-prev]');
        var nextBtn = stage.querySelector('[data-journey-next]');
        var playBtn = stage.querySelector('[data-journey-play]');
        var maxLevel = Math.max.apply(null, order.map(function (item) { return item.level; }).concat([0]));
        var levelCount = maxLevel + 1;

        var index = 0;
        var timer = null;

        if (trail) {
            trail.innerHTML = '';
            for (var level = 0; level <= maxLevel; level++) {
                var item = document.createElement('li');
                item.textContent = shortGenerationLabel(level);
                item.setAttribute('data-trail-level', String(level));
                trail.appendChild(item);
            }
        }

        function stopPlayback() {
            if (timer !== null) {
                window.clearInterval(timer);
                timer = null;
            }
            if (playBtn) {
                playBtn.textContent = 'Play the journey';
                playBtn.setAttribute('data-playing', 'false');
                playBtn.setAttribute('aria-pressed', 'false');
            }
        }

        function startPlayback() {
            stopPlayback();
            if (index >= order.length - 1) {
                render(0);
            }
            if (playBtn) {
                playBtn.textContent = 'Pause';
                playBtn.setAttribute('data-playing', 'true');
                playBtn.setAttribute('aria-pressed', 'true');
            }
            timer = window.setInterval(function () {
                if (index >= order.length - 1) {
                    stopPlayback();
                    return;
                }
                render(index + 1);
            }, 2800);
        }

        function render(nextIndex) {
            index = Math.max(0, Math.min(order.length - 1, nextIndex));
            var entry = order[index];
            var node = nodes[entry.id];
            if (!node) {
                return;
            }

            setText(kicker, labelByLevel[entry.level] || generationLabel(entry.level));
            setText(nameEl, node.name);
            setText(yearsEl, yearsText(node));
            setText(recordEl, statusLabel(node.certainty));
            setText(generationEl, node.generation);
            setText(branchEl, node.branch || 'Not recorded');
            setText(placeEl, placeText(node));
            setText(levelEl, entry.level);

            if (card) {
                card.className = 'journey-stage__card journey-stage__card--' + node.certainty;
                card.classList.add('is-replaying');
                // Force a reflow so the reveal animation runs for each person.
                void card.offsetWidth;
                card.classList.remove('is-replaying');
            }

            if (noteEl) {
                var parentNames = (node.parents || []).map(function (id) {
                    return nodes[id] ? nodes[id].name : null;
                }).filter(Boolean);
                var note;
                if (parentNames.length) {
                    note = 'Parents recorded in this view: ' + parentNames.join(' and ') + '.';
                } else if (entry.level >= maxLevel) {
                    note = 'This is the oldest generation in view. No parents are recorded beyond this point.';
                } else {
                    note = 'No parents are recorded for this person. The branch stays blank rather than guessing.';
                }
                if (node.collapsed) {
                    note += ' This ancestor is shared across ' + node.multiplicity + ' lineage paths.';
                }
                noteEl.textContent = note;
            }

            var pct = levelCount > 1 ? ((entry.level + 1) / levelCount) * 100 : 100;
            if (progress) {
                progress.style.width = pct.toFixed(2) + '%';
            }
            if (progressBar) {
                progressBar.setAttribute('aria-valuenow', String(entry.level));
            }
            if (trail) {
                Array.prototype.forEach.call(trail.children, function (child) {
                    var trailLevel = parseInt(child.getAttribute('data-trail-level'), 10);
                    child.className = trailLevel === entry.level ? 'is-active' : (trailLevel < entry.level ? 'is-past' : '');
                });
            }

            if (prevBtn) {
                prevBtn.disabled = index === 0;
            }
            if (nextBtn) {
                nextBtn.disabled = index === order.length - 1;
            }
            if (index === order.length - 1) {
                stopPlayback();
            }
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                stopPlayback();
                render(index - 1);
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                stopPlayback();
                render(index + 1);
            });
        }
        if (playBtn) {
            playBtn.addEventListener('click', function () {
                if (timer !== null) {
                    stopPlayback();
                } else {
                    startPlayback();
                }
            });
        }

        render(0);
    }

    /* ---------------------------------------------------------------- tree */
    function initTree(payload) {
        var svg = document.querySelector('[data-journey-svg]');
        var viewport = document.querySelector('[data-journey-viewport]');
        if (!svg || !viewport) {
            return;
        }

        var nodes = {};
        (payload.nodes || []).forEach(function (node) {
            nodes[node.id] = node;
        });

        var detail = document.querySelector('[data-journey-detail]');
        var detailKicker = detail ? detail.querySelector('[data-detail-kicker]') : null;
        var detailName = detail ? detail.querySelector('[data-detail-name]') : null;
        var detailYears = detail ? detail.querySelector('[data-detail-years]') : null;
        var detailStatus = detail ? detail.querySelector('[data-detail-status]') : null;
        var detailList = detail ? detail.querySelector('[data-detail-list]') : null;

        function updateDetail(node) {
            if (!detail || !node) {
                return;
            }
            setText(detailKicker, generationLabel(node.level));
            setText(detailName, node.name);
            setText(detailYears, yearsText(node));
            if (detailStatus) {
                detailStatus.textContent = statusLabel(node.certainty);
                detailStatus.className = 'journey-badge journey-badge--' + node.certainty;
            }
            if (detailList) {
                detailList.innerHTML = '';
                var parentNames = (node.parents || []).map(function (id) {
                    return nodes[id] ? nodes[id].name : null;
                }).filter(Boolean);
                var rows = [
                    ['Generation', node.generation],
                    ['Branch', node.branch || 'Not recorded'],
                    ['Born', node.birth_date_text || (node.birth_year !== null && node.birth_year !== undefined ? node.birth_year : 'Not recorded')],
                    ['Died', node.death_date_text || (node.death_year !== null && node.death_year !== undefined ? node.death_year : 'Not recorded')],
                    ['Parents', parentNames.length ? parentNames.join(', ') : 'Not recorded']
                ];
                if (node.collapsed) {
                    rows.push(['Shared lineage', node.multiplicity + ' paths']);
                }
                rows.forEach(function (row) {
                    var wrap = document.createElement('div');
                    var dt = document.createElement('dt');
                    var dd = document.createElement('dd');
                    dt.textContent = row[0];
                    dd.textContent = String(row[1]);
                    wrap.appendChild(dt);
                    wrap.appendChild(dd);
                    detailList.appendChild(wrap);
                });
            }
        }

        function selectNode(id) {
            var node = nodes[id];
            if (!node) {
                return;
            }
            Array.prototype.forEach.call(svg.querySelectorAll('.journey-node.is-selected'), function (el) {
                el.classList.remove('is-selected');
            });
            var target = svg.querySelector('[data-person-id="' + id + '"]');
            if (target) {
                target.classList.add('is-selected');
            }
            updateDetail(node);
        }

        var suppressClick = false;
        var dragging = false;
        var moved = false;
        var start = { x: 0, y: 0 };
        var origin = { x: 0, y: 0 };

        var baseWidth = Number(payload.layout && payload.layout.width) || 640;
        var baseHeight = Number(payload.layout && payload.layout.height) || 320;
        var view = { x: 0, y: 0, w: baseWidth, h: baseHeight };
        var minWidth = baseWidth * 0.3;
        var maxWidth = baseWidth * 2.4;

        function applyView() {
            svg.setAttribute('viewBox', view.x + ' ' + view.y + ' ' + view.w + ' ' + view.h);
        }

        function zoom(factor) {
            var nextWidth = Math.max(minWidth, Math.min(maxWidth, view.w * factor));
            var ratio = nextWidth / view.w;
            var cx = view.x + view.w / 2;
            var cy = view.y + view.h / 2;
            view.w = nextWidth;
            view.h = view.h * ratio;
            view.x = cx - view.w / 2;
            view.y = cy - view.h / 2;
            applyView();
        }

        function resetView() {
            view = { x: 0, y: 0, w: baseWidth, h: baseHeight };
            applyView();
        }

        var zoomButtons = document.querySelectorAll('[data-journey-zoom]');
        Array.prototype.forEach.call(zoomButtons, function (button) {
            button.addEventListener('click', function () {
                var action = button.getAttribute('data-journey-zoom');
                if (action === 'in') {
                    zoom(0.8);
                } else if (action === 'out') {
                    zoom(1.25);
                } else {
                    resetView();
                }
            });
        });

        Array.prototype.forEach.call(svg.querySelectorAll('[data-person-id]'), function (group) {
            group.addEventListener('click', function () {
                if (suppressClick) {
                    return;
                }
                selectNode(group.getAttribute('data-person-id'));
            });
            group.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
                    event.preventDefault();
                    selectNode(group.getAttribute('data-person-id'));
                }
            });
        });

        viewport.addEventListener('pointerdown', function (event) {
            if (event.button !== undefined && event.button !== 0) {
                return;
            }
            dragging = true;
            moved = false;
            start.x = event.clientX;
            start.y = event.clientY;
            origin.x = view.x;
            origin.y = view.y;
        });

        window.addEventListener('pointermove', function (event) {
            if (!dragging) {
                return;
            }
            var dx = event.clientX - start.x;
            var dy = event.clientY - start.y;
            if (Math.abs(dx) + Math.abs(dy) > 4) {
                moved = true;
                viewport.classList.add('is-panning');
            }
            if (!moved) {
                return;
            }
            var scale = view.w / Math.max(1, viewport.clientWidth);
            view.x = origin.x - dx * scale;
            view.y = origin.y - dy * scale;
            applyView();
        });

        function endDrag() {
            if (!dragging) {
                return;
            }
            dragging = false;
            viewport.classList.remove('is-panning');
            if (moved) {
                suppressClick = true;
                window.setTimeout(function () {
                    suppressClick = false;
                }, 0);
            }
        }

        viewport.addEventListener('pointerup', endDrag);
        viewport.addEventListener('pointercancel', endDrag);
        window.addEventListener('pointerup', endDrag);
        window.addEventListener('pointercancel', endDrag);

        viewport.addEventListener('keydown', function (event) {
            var step = view.w * 0.08;
            var handled = true;
            if (event.key === 'ArrowLeft') {
                view.x -= step;
            } else if (event.key === 'ArrowRight') {
                view.x += step;
            } else if (event.key === 'ArrowUp') {
                view.y -= step;
            } else if (event.key === 'ArrowDown') {
                view.y += step;
            } else if (event.key === '+' || event.key === '=') {
                zoom(0.8);
            } else if (event.key === '-') {
                zoom(1.25);
            } else if (event.key === '0') {
                resetView();
            } else {
                handled = false;
            }
            if (handled) {
                event.preventDefault();
                applyView();
            }
        });

        var rootId = payload.meta ? payload.meta.root_id : null;
        if (rootId !== null && nodes[rootId]) {
            selectNode(rootId);
        }
    }

    function init() {
        var payload = readPayload();
        if (!payload) {
            return;
        }
        initAnimate(payload);
        initTree(payload);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
