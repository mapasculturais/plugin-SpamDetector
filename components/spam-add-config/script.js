app.component('spam-add-config', {
    template: $TEMPLATES['spam-add-config'],

    setup() {
        const messages = useMessages();
        const text = Utils.getTexts('spam-add-config')
        return { text, messages }
    },

    computed: {
        spamTerms() {
            return $MAPAS.config.spamAddConfig.spamTerms;
        },
    },

    data() {
        const terms = $MAPAS.config.spamAddConfig.spamTerms || {};
        return {
            notificationTags: [...(terms.notification || [])].sort(),
            blockedTags: [...(terms.blocked || [])].sort(),
            saving: false,
            savePending: false,
        };
    },

    methods: {
        change(event, type) {
            if (event.key === 'Enter' || event.key === 'Tab') {
                this.addTerms(event, type);
                event.preventDefault();
            }
        },

        check(value, type) {
            let index = null;
            let _type = null;

            if (type == "notificationTags") {
                index = this.blockedTags.indexOf(value);
                _type = "blockedTags";
            } else {
                index = this.notificationTags.indexOf(value);
                _type = "notificationTags";
            }

            if (index !== null && index !== -1) {
                this[_type].splice(index, 1);
            }
        },

        addTerms(event, type) {
            const value = event.target.value.trim();
            if (!value) {
                return;
            }

            // bulk entry: "tag1;tag2;tag3" + Enter saves all terms at once
            const terms = value.split(';').map(term => term.trim()).filter(term => term !== '');
            if (!terms.length) {
                return;
            }

            const duplicates = [];

            for (const term of terms) {
                if (this[type].includes(term)) {
                    duplicates.push(term);
                    continue;
                }

                this.check(term, type);
                this[type].push(term);
            }

            if (duplicates.length === 1) {
                this.messages.error(`${this.text('O termo')} ${duplicates[0]} ${this.text('já esta cadastrado')}`);
            } else if (duplicates.length > 1) {
                this.messages.error(`${this.text('Os termos')} ${duplicates.join(', ')} ${this.text('já estão cadastrados')}`);
            }

            this.clear(event);
            this.saveTags();
        },

        clear(event) {
            event.target.value = '';
        },

        async saveTags() {
            // serialized save worker: at most one POST in flight; new calls
            // while saving just mark pending — the loop always re-sends the
            // LATEST snapshot, so concurrent saves can never persist stale data
            this.savePending = true;

            if (this.saving) {
                return;
            }

            this.saving = true;
            let failure = null;

            while (this.savePending) {
                this.savePending = false;

                try {
                    const tagsData = {
                        notification: [...this.notificationTags],
                        blocked: [...this.blockedTags]
                    };

                    const api = new API();
                    const url = Utils.createUrl("spamdetector", "saveterms");
                    const res = await api.POST(url, tagsData);
                    const data = await res.json();

                    if (!res.ok || data.error) {
                        throw new Error(data.error || `HTTP ${res.status}`);
                    }
                } catch (err) {
                    failure = err;
                    break;
                }
            }

            this.saving = false;

            if (failure) {
                this.messages.error(this.text('Falha ao salvar os termos. Verifique se você ainda está autenticado e tente novamente.'));
            } else {
                this.messages.success(this.text('Bloqueio e notificações foi salvo com sucesso'));
            }
        },
    },

});
