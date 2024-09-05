app.component('spam-warning', {
    template: $TEMPLATES['spam-warning'],

    props: {
        entity: {
            type: Entity,
            required: true,
        },
    },

    data() {
        return {
            spamStatus: this.entity?.spam_status ?? true
        }
    },

    methods: {
        setSpamStatus() {
            this.entity.spam_status = this.spamStatus;
            this.entity.save();
        }
    },
});