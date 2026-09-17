/**
 * Alpine component behind the Clinical Workspace canvases. The server sends a
 * node *tree* (patient → encounters → groups → items → doses); this component
 * lays it out left-to-right, draws the parent→child connectors, and lets the
 * user collapse subtrees, expand details, nudge nodes, pan/zoom, and annotate
 * with sticky notes and custom connectors. Collapse/expand state, nudges,
 * notes and annotations persist through `$wire.saveLayout()`.
 *
 * Published by `php artisan filament:assets` — keep this file free of imports.
 */
export default function clinicalCanvas(config) {
    const ZOOM_MIN = 0.2
    const ZOOM_MAX = 2.5
    const SAVE_DEBOUNCE_MS = 800
    const NOTE_W = 220
    const NOTE_H = 140

    const DEFAULT_SIZES = {
        patient: { w: 280, h: 96 },
        encounter: { w: 280, h: 96 },
        group: { w: 220, h: 44 },
        item: { w: 280, h: 64 },
        medication: { w: 280, h: 132 },
        dose: { w: 72, h: 44 },
        note: { w: NOTE_W, h: NOTE_H },
    }

    return {
        canvasKey: config.canvasKey,
        classes: config.classes ?? {},
        icons: config.icons ?? {},
        readOnly: Boolean(config.readOnly),
        gapX: config.gapX ?? 56,
        gapY: config.gapY ?? 14,
        gridCols: config.gridCols ?? 7,
        gridGap: config.gridGap ?? 6,

        root: null,
        index: {},
        parentOf: {},
        depthOf: {},
        sizes: {},
        positions: {},
        gridBlocks: {},
        visible: [],
        edges: [],
        meta: config.meta ?? {},
        layout: { viewport: null, nodes: {}, notes: [], edges: [] },

        viewport: { x: 0, y: 0, zoom: 1 },
        mode: 'select',
        selection: null,
        drag: null,
        connectFrom: null,
        pointer: { x: 0, y: 0 },
        spaceHeld: false,
        saveState: 'idle',
        saveTimer: null,
        relayoutScheduled: false,
        clock: { serverNow: Date.now(), receivedAt: Date.now(), tick: 0 },
        clockTimer: null,
        resizeObserver: null,
        observed: new WeakMap(),

        init() {
            this.hydrateLayout(config.layout)
            this.syncClock(config.meta ?? {})
            this.setTree(config.root)

            if (this.layout.viewport) {
                this.viewport = { ...this.layout.viewport }
            } else {
                this.$nextTick(() => this.initialViewport())
            }

            this.resizeObserver = new ResizeObserver((entries) => {
                let changed = false

                for (const entry of entries) {
                    const id = this.observed.get(entry.target)

                    if (!id) {
                        continue
                    }

                    const w = Math.round(entry.target.offsetWidth)
                    const h = Math.round(entry.target.offsetHeight)
                    const current = this.sizes[id]

                    if (!current || current.w !== w || current.h !== h) {
                        this.sizes[id] = { w, h }
                        changed = true
                    }
                }

                if (changed) {
                    this.scheduleRelayout()
                }
            })

            this.clockTimer = setInterval(() => {
                this.clock.tick++
            }, 30000)

            this.$wire.$watch('canvasTree', (tree) => this.syncTree(tree))
            this.$wire.$watch('canvasMeta', (meta) => this.syncClock(meta ?? {}))

            const flush = () => this.flushSave()
            window.addEventListener('beforeunload', flush)
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'hidden') {
                    flush()
                }
            })
        },

        destroy() {
            clearInterval(this.clockTimer)
            clearTimeout(this.saveTimer)
            this.resizeObserver?.disconnect()
        },

        // ------------------------------------------------------------------
        // Tree data
        // ------------------------------------------------------------------

        hydrateLayout(saved) {
            if (!saved || typeof saved !== 'object') {
                return
            }

            this.layout = {
                viewport: saved.viewport && Number.isFinite(saved.viewport.zoom) ? { ...saved.viewport } : null,
                nodes: { ...(saved.nodes ?? {}) },
                notes: Array.isArray(saved.notes) ? saved.notes.map((note) => ({ ...note })) : [],
                edges: Array.isArray(saved.edges) ? saved.edges.map((edge) => ({ ...edge })) : [],
            }
        },

        setTree(root) {
            this.root = root ?? null
            this.index = {}
            this.parentOf = {}
            this.depthOf = {}

            if (this.root) {
                this.walk(this.root, null, 0)
            }

            this.relayout()
        },

        syncTree(root) {
            if (!root) {
                return
            }

            this.setTree(root)
        },

        walk(node, parentId, depth) {
            this.index[node.id] = node
            this.parentOf[node.id] = parentId
            this.depthOf[node.id] = depth

            for (const child of node.children ?? []) {
                this.walk(child, node.id, depth + 1)
            }
        },

        syncClock(meta) {
            this.meta = meta
            const parsed = meta.now ? Date.parse(meta.now) : NaN
            this.clock.serverNow = Number.isNaN(parsed) ? Date.now() : parsed
            this.clock.receivedAt = Date.now()
            this.clock.tick++
        },

        nowMs() {
            void this.clock.tick

            return this.clock.serverNow + (Date.now() - this.clock.receivedAt)
        },

        nodeState(id) {
            return this.layout.nodes[id] ?? {}
        },

        hasChildren(node) {
            return Array.isArray(node.children) && node.children.length > 0
        },

        isCollapsed(id) {
            const state = this.nodeState(id)

            if (typeof state.collapsed === 'boolean') {
                return state.collapsed
            }

            return Boolean(this.index[id]?.defaultCollapsed)
        },

        isExpanded(id) {
            return this.nodeState(id).expanded === true
        },

        setNodeState(id, patch) {
            this.layout.nodes[id] = { ...this.nodeState(id), ...patch }
        },

        toggleCollapse(id) {
            const node = this.index[id]

            if (!node || !this.hasChildren(node)) {
                return
            }

            this.setNodeState(id, { collapsed: !this.isCollapsed(id) })
            this.relayout()
            this.markDirty()
        },

        toggleExpanded(id) {
            this.setNodeState(id, { expanded: !this.isExpanded(id) })
            this.markDirty()
        },

        expandAll() {
            for (const id of Object.keys(this.index)) {
                if (this.hasChildren(this.index[id])) {
                    this.setNodeState(id, { collapsed: false })
                }
            }

            this.relayout()
            this.markDirty()
        },

        collapseAll() {
            for (const id of Object.keys(this.index)) {
                if (this.hasChildren(this.index[id]) && this.depthOf[id] > 0) {
                    this.setNodeState(id, { collapsed: true })
                }
            }

            this.relayout()
            this.markDirty()
            this.$nextTick(() => this.fitToView())
        },

        observe(id, el) {
            this.observed.set(el, id)
            this.resizeObserver?.observe(el)
        },

        // ------------------------------------------------------------------
        // Layout
        // ------------------------------------------------------------------

        sizeOf(id) {
            const node = this.index[id]

            return this.sizes[id] ?? DEFAULT_SIZES[node?.kind] ?? DEFAULT_SIZES.item
        },

        scheduleRelayout() {
            if (this.relayoutScheduled) {
                return
            }

            this.relayoutScheduled = true
            requestAnimationFrame(() => {
                this.relayoutScheduled = false
                this.relayout()
            })
        },

        relayout() {
            const positions = {}
            const gridBlocks = {}
            const visible = []
            const edges = []
            const heights = {}

            if (!this.root) {
                this.positions = positions
                this.gridBlocks = gridBlocks
                this.visible = visible
                this.edges = edges

                return
            }

            const visibleChildren = (node) => (this.isCollapsed(node.id) ? [] : node.children ?? [])

            const gridDims = (node) => {
                const n = node.children.length
                const cols = Math.min(this.gridCols, n)
                const rows = Math.ceil(n / cols)
                const cell = DEFAULT_SIZES.dose

                return {
                    cols,
                    rows,
                    cell,
                    w: cols * cell.w + (cols - 1) * this.gridGap,
                    h: rows * cell.h + (rows - 1) * this.gridGap,
                }
            }

            const subtreeHeight = (node) => {
                if (heights[node.id] !== undefined) {
                    return heights[node.id]
                }

                const own = this.sizeOf(node.id).h
                const children = visibleChildren(node)
                let height = own

                if (children.length > 0) {
                    if (node.childLayout === 'grid') {
                        height = Math.max(own, gridDims(node).h)
                    } else {
                        const block = children.reduce((sum, child) => sum + subtreeHeight(child), 0) + this.gapY * (children.length - 1)
                        height = Math.max(own, block)
                    }
                }

                heights[node.id] = height

                return height
            }

            const place = (node, x, y, offsetX, offsetY) => {
                const state = this.nodeState(node.id)
                const ox = offsetX + (state.dx ?? 0)
                const oy = offsetY + (state.dy ?? 0)
                const size = this.sizeOf(node.id)
                const total = subtreeHeight(node)
                const children = visibleChildren(node)

                positions[node.id] = { x: x + ox, y: y + (total - size.h) / 2 + oy }
                visible.push(node)

                if (children.length === 0) {
                    return
                }

                const childX = x + size.w + this.gapX

                if (node.childLayout === 'grid') {
                    const dims = gridDims(node)
                    const blockY = y + (total - dims.h) / 2

                    gridBlocks[node.id] = { x: childX + ox, y: blockY + oy, w: dims.w, h: dims.h }
                    edges.push({ id: `grid_${node.id}`, from: node.id, to: node.id, toBlock: true })

                    children.forEach((child, i) => {
                        const col = i % dims.cols
                        const row = Math.floor(i / dims.cols)
                        positions[child.id] = {
                            x: childX + ox + col * (dims.cell.w + this.gridGap),
                            y: blockY + oy + row * (dims.cell.h + this.gridGap),
                        }
                        visible.push(child)
                    })

                    return
                }

                const block = children.reduce((sum, child) => sum + subtreeHeight(child), 0) + this.gapY * (children.length - 1)
                let cursor = y + (total - block) / 2

                for (const child of children) {
                    place(child, childX, cursor, ox, oy)
                    edges.push({ id: `tree_${child.id}`, from: node.id, to: child.id })
                    cursor += subtreeHeight(child) + this.gapY
                }
            }

            place(this.root, 40, 40, 0, 0)

            this.positions = positions
            this.gridBlocks = gridBlocks
            this.visible = visible
            this.edges = edges
        },

        position(id) {
            return this.positions[id] ?? { x: 0, y: 0 }
        },

        boxOf(id) {
            const note = this.layout.notes.find((candidate) => candidate.id === id)

            if (note) {
                return { x: note.x, y: note.y, w: note.w, h: note.h }
            }

            const position = this.position(id)
            const size = this.sizeOf(id)

            return { x: position.x, y: position.y, w: size.w, h: size.h }
        },

        treeEdgePath(edge) {
            const from = this.boxOf(edge.from)
            const to = edge.toBlock ? this.gridBlocks[edge.from] : this.boxOf(edge.to)

            if (!to) {
                return ''
            }

            const start = { x: from.x + from.w, y: from.y + from.h / 2 }
            const end = { x: to.x, y: to.y + to.h / 2 }
            const bend = Math.max(24, (end.x - start.x) / 2)

            return `M ${start.x} ${start.y} C ${start.x + bend} ${start.y}, ${end.x - bend} ${end.y}, ${end.x} ${end.y}`
        },

        contentBounds() {
            const boxes = [
                ...this.visible.map((node) => this.boxOf(node.id)),
                ...this.layout.notes.map((note) => this.boxOf(note.id)),
            ]

            if (boxes.length === 0) {
                return { x: 0, y: 0, w: 800, h: 400 }
            }

            const minX = Math.min(...boxes.map((box) => box.x))
            const minY = Math.min(...boxes.map((box) => box.y))
            const maxX = Math.max(...boxes.map((box) => box.x + box.w))
            const maxY = Math.max(...boxes.map((box) => box.y + box.h))

            return { x: minX, y: minY, w: maxX - minX, h: maxY - minY }
        },

        // ------------------------------------------------------------------
        // Viewport
        // ------------------------------------------------------------------

        rootRect() {
            return this.$root.getBoundingClientRect()
        },

        screenToWorld(clientX, clientY) {
            const rect = this.rootRect()

            return {
                x: (clientX - rect.left - this.viewport.x) / this.viewport.zoom,
                y: (clientY - rect.top - this.viewport.y) / this.viewport.zoom,
            }
        },

        worldTransform() {
            return `translate(${this.viewport.x}px, ${this.viewport.y}px) scale(${this.viewport.zoom})`
        },

        initialViewport() {
            const bounds = this.contentBounds()
            const rect = this.rootRect()

            if (bounds.w * 1 <= rect.width - 80 && bounds.h <= rect.height - 80) {
                this.fitToView(false)

                return
            }

            // Anchor the root at the left, 1:1, so the first level is readable.
            this.viewport = { x: 24 - bounds.x, y: Math.max(24, (rect.height - bounds.h) / 2) - bounds.y, zoom: 1 }
        },

        fitToView(persist = true) {
            const bounds = this.contentBounds()
            const rect = this.rootRect()
            const padding = 40
            const zoom = clamp(
                Math.min((rect.width - padding * 2) / bounds.w, (rect.height - padding * 2) / bounds.h, 1.25),
                ZOOM_MIN,
                ZOOM_MAX,
            )

            this.viewport = {
                x: (rect.width - bounds.w * zoom) / 2 - bounds.x * zoom,
                y: (rect.height - bounds.h * zoom) / 2 - bounds.y * zoom,
                zoom,
            }

            if (persist) {
                this.markDirty()
            }
        },

        focusNode(id) {
            const box = this.boxOf(id)
            const rect = this.rootRect()
            const zoom = Math.max(this.viewport.zoom, 1)

            this.viewport = {
                x: rect.width / 2 - (box.x + box.w / 2) * zoom,
                y: rect.height / 2 - (box.y + box.h / 2) * zoom,
                zoom,
            }

            this.selection = { type: 'node', id }
            this.markDirty()
        },

        zoomBy(factor, clientX = null, clientY = null) {
            const rect = this.rootRect()
            const sx = clientX === null ? rect.width / 2 : clientX - rect.left
            const sy = clientY === null ? rect.height / 2 : clientY - rect.top
            const wx = (sx - this.viewport.x) / this.viewport.zoom
            const wy = (sy - this.viewport.y) / this.viewport.zoom
            const zoom = clamp(this.viewport.zoom * factor, ZOOM_MIN, ZOOM_MAX)

            this.viewport = { x: sx - wx * zoom, y: sy - wy * zoom, zoom }
            this.markDirty()
        },

        zoomIn() {
            this.zoomBy(1.2)
        },

        zoomOut() {
            this.zoomBy(1 / 1.2)
        },

        resetZoom() {
            this.zoomBy(1 / this.viewport.zoom)
        },

        zoomPercent() {
            return `${Math.round(this.viewport.zoom * 100)}%`
        },

        // ------------------------------------------------------------------
        // Pointer interaction
        // ------------------------------------------------------------------

        onWheel(event) {
            event.preventDefault()

            if (event.ctrlKey || event.metaKey) {
                this.zoomBy(Math.exp(-event.deltaY * 0.002), event.clientX, event.clientY)

                return
            }

            this.viewport.x -= event.shiftKey ? event.deltaY : event.deltaX
            this.viewport.y -= event.shiftKey ? 0 : event.deltaY
            this.markDirty()
        },

        onBackgroundPointerDown(event) {
            if (event.button === 2) {
                return
            }

            if (this.mode === 'note' && event.button === 0) {
                const world = this.screenToWorld(event.clientX, event.clientY)
                this.addNote(world.x, world.y)
                this.mode = 'select'

                return
            }

            if (this.mode === 'connect') {
                this.cancelConnect()
            }

            this.selection = null
            this.startPan(event)
        },

        startPan(event) {
            this.drag = {
                kind: 'pan',
                startX: event.clientX,
                startY: event.clientY,
                originX: this.viewport.x,
                originY: this.viewport.y,
            }

            event.currentTarget.setPointerCapture?.(event.pointerId)
        },

        onItemPointerDown(event, id, kind = 'node') {
            if (event.button === 1 || this.spaceHeld) {
                this.startPan(event)

                return
            }

            if (event.button !== 0) {
                return
            }

            if (this.mode === 'connect') {
                event.stopPropagation()
                this.completeConnect(id)

                return
            }

            this.selection = { type: kind, id }

            if (this.readOnly) {
                return
            }

            if (kind === 'node') {
                const state = this.nodeState(id)

                this.drag = {
                    kind,
                    id,
                    startX: event.clientX,
                    startY: event.clientY,
                    originX: state.dx ?? 0,
                    originY: state.dy ?? 0,
                    moved: false,
                }
            } else {
                const box = this.boxOf(id)

                this.drag = {
                    kind,
                    id,
                    startX: event.clientX,
                    startY: event.clientY,
                    originX: box.x,
                    originY: box.y,
                    moved: false,
                }
            }

            event.currentTarget.setPointerCapture?.(event.pointerId)
        },

        onNoteResizePointerDown(event, id) {
            event.stopPropagation()

            const note = this.layout.notes.find((candidate) => candidate.id === id)

            if (!note) {
                return
            }

            this.drag = {
                kind: 'resize',
                id,
                startX: event.clientX,
                startY: event.clientY,
                originW: note.w,
                originH: note.h,
            }

            event.currentTarget.setPointerCapture?.(event.pointerId)
        },

        onPointerMove(event) {
            this.pointer = this.screenToWorld(event.clientX, event.clientY)

            if (!this.drag) {
                return
            }

            const dx = event.clientX - this.drag.startX
            const dy = event.clientY - this.drag.startY

            if (this.drag.kind === 'pan') {
                this.viewport.x = this.drag.originX + dx
                this.viewport.y = this.drag.originY + dy

                return
            }

            const wx = dx / this.viewport.zoom
            const wy = dy / this.viewport.zoom

            if (Math.abs(dx) > 3 || Math.abs(dy) > 3) {
                this.drag.moved = true
            }

            if (!this.drag.moved) {
                return
            }

            if (this.drag.kind === 'node') {
                this.setNodeState(this.drag.id, {
                    dx: Math.round(this.drag.originX + wx),
                    dy: Math.round(this.drag.originY + wy),
                })
                this.relayout()

                return
            }

            const note = this.layout.notes.find((candidate) => candidate.id === this.drag.id)

            if (!note) {
                return
            }

            if (this.drag.kind === 'note') {
                note.x = Math.round(this.drag.originX + wx)
                note.y = Math.round(this.drag.originY + wy)
            } else if (this.drag.kind === 'resize') {
                note.w = Math.max(120, Math.round(this.drag.originW + wx))
                note.h = Math.max(80, Math.round(this.drag.originH + wy))
            }
        },

        onPointerUp() {
            if (!this.drag) {
                return
            }

            const drag = this.drag
            this.drag = null

            if (drag.kind === 'pan' || drag.moved || drag.kind === 'resize') {
                this.markDirty()
            }
        },

        onKeyDown(event) {
            const target = event.target

            if (target && (target.tagName === 'TEXTAREA' || target.tagName === 'INPUT' || target.isContentEditable)) {
                return
            }

            switch (event.key) {
                case ' ':
                    this.spaceHeld = true
                    event.preventDefault()
                    break
                case 'Escape':
                    this.mode = 'select'
                    this.cancelConnect()
                    this.selection = null
                    break
                case 'Delete':
                case 'Backspace':
                    this.deleteSelection()
                    break
                case 'Enter':
                    if (this.selection?.type === 'node') {
                        this.toggleCollapse(this.selection.id)
                    }
                    break
                case 'f':
                case 'F':
                    this.fitToView()
                    break
                case '0':
                    this.resetZoom()
                    break
                case '+':
                case '=':
                    this.zoomIn()
                    break
                case '-':
                    this.zoomOut()
                    break
                case 'n':
                case 'N':
                    this.toggleMode('note')
                    break
                case 'c':
                case 'C':
                    this.toggleMode('connect')
                    break
            }
        },

        onKeyUp(event) {
            if (event.key === ' ') {
                this.spaceHeld = false
            }
        },

        toggleMode(mode) {
            if (this.readOnly) {
                return
            }

            this.mode = this.mode === mode ? 'select' : mode

            if (this.mode !== 'connect') {
                this.cancelConnect()
            }
        },

        cursorClass() {
            if (this.drag?.kind === 'pan') {
                return 'cursor-grabbing'
            }

            if (this.spaceHeld) {
                return 'cursor-grab'
            }

            if (this.mode === 'note') {
                return 'cursor-copy'
            }

            if (this.mode === 'connect') {
                return 'cursor-crosshair'
            }

            return 'cursor-default'
        },

        isSelected(id) {
            return this.selection?.id === id
        },

        openUrl(node) {
            if (node.url) {
                window.open(node.url, '_blank', 'noopener')
            }
        },

        typeClass(type, part) {
            return this.classes.type?.[type]?.[part] ?? this.classes.type?.other?.[part] ?? ''
        },

        badgeClass(color) {
            return this.classes.badge?.[color] ?? this.classes.badge?.gray ?? ''
        },

        // ------------------------------------------------------------------
        // Medication helpers
        // ------------------------------------------------------------------

        relativeLabel(iso) {
            const diff = Date.parse(iso) - this.nowMs()
            const minutes = Math.round(Math.abs(diff) / 60000)
            const hours = Math.floor(minutes / 60)
            const rest = minutes % 60
            const span = hours > 0 ? `${hours}h ${rest}m` : `${rest}m`

            return diff >= 0 ? `in ${span}` : `${span} overdue`
        },

        slotClass(status) {
            return this.classes.slot?.[status] ?? this.classes.slot?.upcoming ?? ''
        },

        doseTitle(node) {
            const parent = this.index[this.parentOf[node.id]]
            const lines = [`${parent?.title ?? 'Medication'} — ${node.sequence ? `dose #${node.sequence}` : 'PRN dose'}`, `Due ${node.dueAtLabel}`]

            if (node.administration) {
                lines.push(this.administrationTitle(node.administration))
            } else if (node.actionable) {
                lines.push('Click to record this dose')
            } else if (node.status === 'overdue') {
                lines.push('Missed — only the next due dose can be recorded')
            }

            return lines.join('\n')
        },

        administrationTitle(administration) {
            const parts = [`${administration.statusLabel} ${administration.startedAtLabel}`]

            if (administration.by) {
                parts.push(`by ${administration.by}`)
            }

            if (administration.quantity) {
                parts.push(`${administration.quantity} ${administration.unit ?? ''}`.trim())
            }

            if (administration.reason) {
                parts.push(administration.reason)
            }

            return parts.join(' · ')
        },

        recordDose(node) {
            if (this.readOnly) {
                return
            }

            if (node.kind === 'dose') {
                if (!node.actionable) {
                    return
                }

                this.$wire.mountAction('recordDose', { requestItemId: node.requestItemId })

                return
            }

            if (node.canRecord) {
                this.$wire.mountAction('recordDose', { requestItemId: node.requestItemId })
            }
        },

        // ------------------------------------------------------------------
        // Sticky notes
        // ------------------------------------------------------------------

        noteColors: ['amber', 'sky', 'emerald', 'rose', 'violet'],

        addNote(x = null, y = null) {
            if (this.readOnly) {
                return
            }

            if (x === null || y === null) {
                const rect = this.rootRect()
                const center = this.screenToWorld(rect.left + rect.width / 2, rect.top + rect.height / 2)
                x = center.x - NOTE_W / 2
                y = center.y - NOTE_H / 2
            }

            const note = {
                id: `note_${Math.random().toString(36).slice(2, 10)}`,
                x: Math.round(x),
                y: Math.round(y),
                w: NOTE_W,
                h: NOTE_H,
                text: '',
                color: 'amber',
            }

            this.layout.notes.push(note)
            this.selection = { type: 'note', id: note.id }
            this.markDirty()

            this.$nextTick(() => {
                this.$root.querySelector(`[data-note-id="${note.id}"] textarea`)?.focus()
            })
        },

        cycleNoteColor(id) {
            const note = this.layout.notes.find((candidate) => candidate.id === id)

            if (!note) {
                return
            }

            note.color = this.noteColors[(this.noteColors.indexOf(note.color) + 1) % this.noteColors.length]
            this.markDirty()
        },

        removeNote(id) {
            this.layout.notes = this.layout.notes.filter((note) => note.id !== id)
            this.layout.edges = this.layout.edges.filter((edge) => edge.from !== id && edge.to !== id)

            if (this.selection?.id === id) {
                this.selection = null
            }

            this.markDirty()
        },

        noteClass(note) {
            return this.classes.note?.[note.color] ?? this.classes.note?.amber ?? ''
        },

        // ------------------------------------------------------------------
        // User connectors
        // ------------------------------------------------------------------

        completeConnect(id) {
            if (!this.connectFrom) {
                this.connectFrom = id

                return
            }

            if (this.connectFrom !== id) {
                const exists = this.layout.edges.some(
                    (edge) => (edge.from === this.connectFrom && edge.to === id) || (edge.from === id && edge.to === this.connectFrom),
                )

                if (!exists) {
                    this.layout.edges.push({
                        id: `edge_${Math.random().toString(36).slice(2, 10)}`,
                        from: this.connectFrom,
                        to: id,
                        label: '',
                    })
                    this.markDirty()
                }
            }

            this.connectFrom = null
            this.mode = 'select'
        },

        cancelConnect() {
            this.connectFrom = null
        },

        removeEdge(id) {
            this.layout.edges = this.layout.edges.filter((edge) => edge.id !== id)

            if (this.selection?.id === id) {
                this.selection = null
            }

            this.markDirty()
        },

        setEdgeLabel(id, label) {
            const edge = this.layout.edges.find((candidate) => candidate.id === id)

            if (edge) {
                edge.label = label.slice(0, 120)
                this.markDirty()
            }
        },

        edgePath(edge) {
            return bezierBetween(this.boxOf(edge.from), this.boxOf(edge.to))
        },

        draftPath() {
            if (!this.connectFrom) {
                return ''
            }

            return bezierBetween(this.boxOf(this.connectFrom), { x: this.pointer.x, y: this.pointer.y, w: 0, h: 0 })
        },

        edgeMidpoint(edge) {
            const from = this.boxOf(edge.from)
            const to = this.boxOf(edge.to)

            return {
                x: (from.x + from.w / 2 + to.x + to.w / 2) / 2,
                y: (from.y + from.h / 2 + to.y + to.h / 2) / 2,
            }
        },

        visibleUserEdges() {
            const ids = new Set([...this.visible.map((node) => node.id), ...this.layout.notes.map((note) => note.id)])

            return this.layout.edges.filter((edge) => ids.has(edge.from) && ids.has(edge.to))
        },

        deleteSelection() {
            if (!this.selection || this.readOnly) {
                return
            }

            if (this.selection.type === 'note') {
                this.removeNote(this.selection.id)
            } else if (this.selection.type === 'edge') {
                this.removeEdge(this.selection.id)
            }
        },

        // ------------------------------------------------------------------
        // Persistence
        // ------------------------------------------------------------------

        serializeLayout() {
            const nodes = {}

            for (const [id, state] of Object.entries(this.layout.nodes)) {
                if (!this.index[id]) {
                    continue
                }

                const entry = {}

                if (typeof state.collapsed === 'boolean') {
                    entry.collapsed = state.collapsed
                }

                if (state.expanded === true) {
                    entry.expanded = true
                }

                if (state.dx) {
                    entry.dx = Math.round(state.dx)
                }

                if (state.dy) {
                    entry.dy = Math.round(state.dy)
                }

                if (Object.keys(entry).length > 0) {
                    nodes[id] = entry
                }
            }

            return {
                viewport: {
                    x: Math.round(this.viewport.x * 100) / 100,
                    y: Math.round(this.viewport.y * 100) / 100,
                    zoom: Math.round(this.viewport.zoom * 1000) / 1000,
                },
                nodes,
                notes: this.layout.notes.map((note) => ({
                    id: note.id,
                    x: Math.round(note.x),
                    y: Math.round(note.y),
                    w: Math.round(note.w),
                    h: Math.round(note.h),
                    text: note.text ?? '',
                    color: note.color,
                })),
                edges: this.layout.edges
                    .filter((edge) => (this.index[edge.from] || this.layout.notes.some((n) => n.id === edge.from)) && (this.index[edge.to] || this.layout.notes.some((n) => n.id === edge.to)))
                    .map((edge) => ({ id: edge.id, from: edge.from, to: edge.to, label: edge.label ?? '' })),
            }
        },

        markDirty() {
            if (this.readOnly) {
                return
            }

            this.saveState = 'dirty'
            clearTimeout(this.saveTimer)
            this.saveTimer = setTimeout(() => this.flushSave(), SAVE_DEBOUNCE_MS)
        },

        async flushSave() {
            if (this.saveState !== 'dirty' || this.readOnly) {
                return
            }

            clearTimeout(this.saveTimer)
            this.saveState = 'saving'

            try {
                await this.$wire.saveLayout(this.serializeLayout())
                this.saveState = 'saved'
            } catch (error) {
                console.error('Canvas layout save failed', error)
                this.saveState = 'error'
            }
        },

        async resetLayout() {
            if (this.readOnly) {
                return
            }

            clearTimeout(this.saveTimer)
            this.saveState = 'idle'
            await this.$wire.resetLayout()
            this.layout = { viewport: null, nodes: {}, notes: [], edges: [] }
            this.selection = null
            this.relayout()
            this.$nextTick(() => this.initialViewport())
        },
    }

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value))
    }

    function bezierBetween(from, to) {
        const fromCenter = { x: from.x + from.w / 2, y: from.y + from.h / 2 }
        const toCenter = { x: to.x + to.w / 2, y: to.y + to.h / 2 }
        const dx = toCenter.x - fromCenter.x
        const dy = toCenter.y - fromCenter.y
        let start
        let end
        let c1
        let c2

        if (Math.abs(dx) >= Math.abs(dy)) {
            const rightward = dx >= 0
            start = { x: rightward ? from.x + from.w : from.x, y: fromCenter.y }
            end = { x: rightward ? to.x : to.x + to.w, y: toCenter.y }
            const bend = Math.max(40, Math.abs(end.x - start.x) / 2)
            c1 = { x: start.x + (rightward ? bend : -bend), y: start.y }
            c2 = { x: end.x + (rightward ? -bend : bend), y: end.y }
        } else {
            const downward = dy >= 0
            start = { x: fromCenter.x, y: downward ? from.y + from.h : from.y }
            end = { x: toCenter.x, y: downward ? to.y : to.y + to.h }
            const bend = Math.max(40, Math.abs(end.y - start.y) / 2)
            c1 = { x: start.x, y: start.y + (downward ? bend : -bend) }
            c2 = { x: end.x, y: end.y + (downward ? -bend : bend) }
        }

        return `M ${start.x} ${start.y} C ${c1.x} ${c1.y}, ${c2.x} ${c2.y}, ${end.x} ${end.y}`
    }
}
