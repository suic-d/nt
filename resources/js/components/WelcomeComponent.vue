<template>
    <button @click="count++">You Clicked me {{ count }} times.</button>
</template>
<script>
export default {
    data() {
        return {
            count:0
        }
    }
}
</script>
