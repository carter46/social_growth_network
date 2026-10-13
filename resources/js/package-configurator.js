import { formatMoney, formatNumber } from './money';

const URL_PATTERN = /^https?:\/\/[^\s.]+\.[^\s]+$/i;

/**
 * Package form on the public and dashboard product pages: quantities per service,
 * live total, and YouTube video / channel checks before checkout.
 */
export function registerPackageConfigurator(Alpine) {
    Alpine.data('packageConfigurator', (config = {}) => ({
        rows: config.rows || [],
        unitPrice: Number(config.unitPrice || 0),
        quantities: {},
        needsTargetUrl: Boolean(config.needsTargetUrl),
        targetIsChannel: Boolean(config.targetIsChannel),
        needsChannel: Boolean(config.needsChannel),
        needsComment: Boolean(config.needsComment),
        commentMax: Number(config.commentMax || 1000),
        targetUrl: config.initial?.targetUrl || '',
        channelUrl: config.initial?.channelUrl || '',
        commentInstructions: config.initial?.commentInstructions || '',
        video: null,
        videoLoading: false,
        videoRequestId: 0,
        channel: null,
        channelVideos: [],
        channelLoading: false,
        channelRequestId: 0,
        customVideo: false,
        videoTimer: null,
        channelTimer: null,
        submitting: false,
        revealed: Boolean(config.revealed),

        init() {
            const initialQty = config.initial?.qty || {};
            this.rows.forEach((row) => {
                const fromInitial = row.primary ? config.initial?.units : initialQty[row.task];
                const value = Number(fromInitial);
                this.quantities[row.task] = Number.isInteger(value) && fromInitial !== '' && fromInitial != null ? value : row.min;
            });
            if (this.needsChannel && this.channelUrl.trim().length > 8) {
                this.lookupChannel();
            }
            if (this.needsTargetUrl && this.targetUrl.trim().length > 8) {
                if (this.targetIsChannel) {
                    this.lookupChannel(this.targetUrl);
                } else {
                    this.customVideo = true;
                    this.lookupVideo();
                }
            }
        },

        get showDetails() {
            return ! this.needsTargetUrl || this.revealed;
        },
        get primaryRow() {
            return this.rows.find((row) => row.primary) || null;
        },
        get companionRows() {
            return this.rows.filter((row) => ! row.primary);
        },
        rowValid(row) {
            const value = Number(this.quantities[row.task]);
            return Number.isInteger(value) && value >= row.min && value <= row.max;
        },
        get quantitiesValid() {
            return this.rows.length > 0 && this.rows.every((row) => this.rowValid(row));
        },
        extraFor(row) {
            if (row.primary) return 0;
            return Math.max(0, (Number(this.quantities[row.task]) || 0) - row.min);
        },
        get extraUnits() {
            return this.companionRows.reduce((sum, row) => sum + this.extraFor(row), 0);
        },
        get billableUnits() {
            const main = this.primaryRow ? Number(this.quantities[this.primaryRow.task]) || 0 : 0;
            return main + this.extraUnits;
        },
        get total() {
            return Math.round(this.unitPrice * this.billableUnits * 100) / 100;
        },
        money(value, max = 2) {
            return formatMoney(value, { precision: 2, max });
        },
        count(value) {
            return formatNumber(value);
        },

        get videoUrlLooksValid() {
            return URL_PATTERN.test(String(this.targetUrl || '').trim());
        },
        get channelMismatch() {
            const videoChannel = this.video?.video?.channel_id;
            const channelId = this.channel?.channel?.id;
            return Boolean(this.needsChannel && videoChannel && channelId && videoChannel !== channelId);
        },
        get linksValid() {
            if (! this.needsTargetUrl) return true;
            if (this.targetIsChannel) {
                if (! URL_PATTERN.test(String(this.targetUrl || '').trim())) return false;
                return ! (this.channel && this.channel.allowed === false);
            }
            if (! this.videoUrlLooksValid) return false;
            if (this.video && this.video.allowed === false) return false;
            if (this.needsChannel) {
                if (! URL_PATTERN.test(String(this.channelUrl || '').trim())) return false;
                if (this.channel && this.channel.allowed === false) return false;
                if (this.channelMismatch) return false;
            }
            if (this.needsComment) {
                const text = String(this.commentInstructions || '').trim();
                if (text.length === 0 || text.length > this.commentMax) return false;
            }
            return true;
        },
        get canSubmit() {
            return this.quantitiesValid && this.linksValid && ! this.videoLoading && ! this.channelLoading && ! this.submitting;
        },
        get blockedReason() {
            if (! this.quantitiesValid) return 'Check the quantities above.';
            if (this.videoLoading || this.channelLoading) return 'Checking your link...';
            if (this.needsTargetUrl && ! this.linksValid) {
                if (this.channelMismatch) return 'Pick a video from the channel above.';
                return 'Add your link and campaign details above to continue.';
            }
            return '';
        },

        onVideoInput() {
            clearTimeout(this.videoTimer);
            this.video = null;
            this.videoTimer = setTimeout(() => this.lookupVideo(), 500);
        },
        onChannelInput() {
            clearTimeout(this.channelTimer);
            this.channel = null;
            this.channelVideos = [];
            this.channelTimer = setTimeout(() => this.lookupChannel(), 500);
        },
        onTargetChannelInput() {
            clearTimeout(this.channelTimer);
            this.channel = null;
            this.channelTimer = setTimeout(() => this.lookupChannel(this.targetUrl), 500);
        },

        async post(url, payload) {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': config.csrf || '',
                },
                body: JSON.stringify(payload),
            });
            if (res.status === 429) {
                return { status: 'unverified', allowed: true, message: 'Too many checks in a row. Wait a moment; we will check your link again at checkout.' };
            }
            const data = await res.json().catch(() => null);
            if (! res.ok || ! data || typeof data !== 'object' || ! data.status) {
                return { status: 'unverified', allowed: true, message: 'We could not check this link right now. We will check it again at checkout.' };
            }
            return data;
        },

        async lookupVideo() {
            const url = String(this.targetUrl || '').trim();
            if (! config.videoPreviewUrl || url.length < 8) {
                this.videoRequestId += 1;
                this.video = null;
                this.videoLoading = false;
                return;
            }
            const requestId = ++this.videoRequestId;
            this.videoLoading = true;
            try {
                const data = await this.post(config.videoPreviewUrl, { url, product_slug: config.productSlug });
                if (requestId !== this.videoRequestId) return;
                this.video = data;
                if (data.allowed !== false) this.revealed = true;
                if (this.needsChannel && ! String(this.channelUrl || '').trim() && data.video?.channel_url) {
                    this.channelUrl = data.video.channel_url;
                    this.lookupChannel();
                }
            } catch {
                if (requestId !== this.videoRequestId) return;
                this.video = { status: 'unverified', allowed: true, message: 'We could not check this link right now. We will check it again at checkout.' };
                this.revealed = true;
            } finally {
                if (requestId === this.videoRequestId) this.videoLoading = false;
            }
        },

        async lookupChannel(override = null) {
            const url = String(override ?? this.channelUrl ?? '').trim();
            if (! config.channelPreviewUrl || url.length < 8) {
                this.channelRequestId += 1;
                this.channel = null;
                this.channelVideos = [];
                this.channelLoading = false;
                return;
            }
            const requestId = ++this.channelRequestId;
            this.channelLoading = true;
            try {
                const data = await this.post(config.channelPreviewUrl, { url, product_slug: config.productSlug });
                if (requestId !== this.channelRequestId) return;
                this.channel = data;
                this.channelVideos = Array.isArray(data.videos) ? data.videos : [];
                if (this.targetIsChannel && data.allowed !== false) this.revealed = true;
                if (! this.targetIsChannel && this.channelVideos.length === 0 && data.allowed !== false) {
                    this.customVideo = true;
                }
            } catch {
                if (requestId !== this.channelRequestId) return;
                this.channel = { status: 'unverified', allowed: true, message: 'We could not check this channel right now. We will check it again at checkout.' };
                this.channelVideos = [];
                this.customVideo = true;
                if (this.targetIsChannel) this.revealed = true;
            } finally {
                if (requestId === this.channelRequestId) this.channelLoading = false;
            }
        },

        isPicked(item) {
            return ! this.customVideo && this.video?.video?.id === item.id;
        },
        pickVideo(item) {
            this.customVideo = false;
            this.targetUrl = item.url;
            this.videoRequestId += 1;
            this.videoLoading = false;
            this.video = { status: 'ok', allowed: true, message: null, video: item };
            this.revealed = true;
        },
        useDifferentVideo() {
            this.customVideo = true;
            this.targetUrl = '';
            this.video = null;
            this.$nextTick(() => this.$refs.videoInput?.focus());
        },
        backToChannelVideos() {
            clearTimeout(this.videoTimer);
            this.videoRequestId += 1;
            this.videoLoading = false;
            this.customVideo = false;
            this.targetUrl = '';
            this.video = null;
        },

        submit(event) {
            if (! this.canSubmit) {
                event.preventDefault();
                return;
            }
            this.submitting = true;
        },
    }));
}
