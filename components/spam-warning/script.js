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
            spamStatus: this.entity?.spam_status ?? false
        }
    },

    methods: {
        setSpamStatus() {
            this.spamStatus = !this.spamStatus;
            this.entity.spam_status = this.spamStatus;
            this.entity.save();
        },

        closeModal(modal) {
            modal.close();
        }
    },

    mounted() {
        if (this.entity.spam_status === null) {
            this.entity.spam_status = false;
        }
    }
});