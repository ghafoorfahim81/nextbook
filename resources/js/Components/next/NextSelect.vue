<template>
    <v-select
        type="any"
        :options.sync="options"
        :label="label ? label : 'name'"
        @search="itemSearch"
        @click="click($event)"
        @input="input($event)"
        :multiple="multiple ? multiple : false"
        :filterable="filterable ? filterable : true"
        :value.sync="value"
        class="select-item w-full select-40px"
        dir="ltr"
        :placeholder="placeholder ? placeholder : 'SearchItem'"
    >
        <!-- <span slot="no-options" @click="$emit('add')" style="cursor: pointer;" >
          {{ $t(textlabel)?$t(textlabel):'Add New' }}
        </span> -->
        <!-- <template #list-footer >
          <div><q-btn id="add"  class="full-width q-ma-none -mb-1 mt-1"  icon="add" color="light-blue-7" @click.prevent="AddClick">{{ $t("AddNew") }}</q-btn></div>
        </template> -->
        <template #no-options>
            <li>No record found!</li>
        </template>
        <template #list-footer v-if="has_footer">
            <li @click="$emit('add_click')" style="text-align: center">
<!--                <q-btn-->
<!--                    id="add"-->
<!--                    class="full-width q-ma-none -mb-1 mt-1"-->
<!--                    icon="add"-->
<!--                    color="light-blue-7"-->
<!--                >{{ $t("AddNew") }}</q-btn-->
                >
            </li>
        </template>
        <template #list-header>
            <li style="text-align: center">{{ (title) }}</li>
        </template>
    </v-select>
</template>

<script>
import vSelect from "vue-select";
export default {
    props: {
        name: {
            type: String,
            default: "",
        },
        textlabel: {
            type: String,
            default: "",
        },
        label: {
            type: String,
            default: "",
        },
        value: {
            type: Object,
            default: null,
        },
        placeholder: {
            type: String,
            default: "",
        },
        title: {
            type: String,
            default: "",
        },
        options: {
            type: Array,
            default: [],
        },
        filterable: {
            type: Boolean,
            default: true,
        },
        has_footer: {
            type: Boolean,
            default: true,
        },
        multiple: {
            type: Boolean,
            default: false,
        },
    },
    // props: ["name", "textlabel", "label", "value",'placeholder', "options", "filterable", 'title',"multiple","has_footer"],
    name: "VnSelect",
    components: {
        vSelect,
    },
    data() {
        return {
            debounce: null,
        };
    },
    computed: {

    },
    methods: {
        itemSearch(search, loading) {
            if (search.length > 0) {
                clearTimeout(this.debounce);
                loading(true);
                this.debounce = setTimeout(() => {
                    // this.$emit("update:value", e);
                    this.$emit("search", search, loading);
                    loading(false);
                }, 350);
            }
        },
        click(e) {
            this.$emit("update:value", e);
            this.$emit("click");
        },
        AddClick() {
            console.log("-----------------------------");
            this.$emit("add_click");
        },
        input(e) {
            this.$emit("update:value", e);
            this.$emit("input");
        },
    },
};
</script>

<style lang="css">
/* apply CSS to the select tag of
      .dropdown-container div*/

.select-item select {
    /* for Firefox */
    -moz-appearance: none;
    /* for Safari, Chrome, Opera */
    -webkit-appearance: none;
}

#add {
    cursor: pointer;
}
/* for IE10 */
.select-item select::-ms-expand {
    display: none;
}

/* .select-40px {
    height: 50px;
  } */
.vs__dropdown-toggle {
    height: 40px;
}
</style>
