webpackJsonp([41],{X2rD:function(t,e){},cqOP:function(t,e,s){"use strict";Object.defineProperty(e,"__esModule",{value:!0});var i=s("Dd8w"),r=s.n(i),c=(s("Au9i"),s("vAZe"),{props:{item:{type:Object}},computed:{getPhotoUrl:function(){var t=null,e=this.item;if(e&&e.product)if(e.product.default_photo)t=e.product.default_photo.thumb;else{var i=e.product.photos;if(i&&i.length){var r=i[0];r&&r.large?t=r.large:r.thumb&&(t=r.thumb)}}return t||(t=s("aVgn")),t},getTitle:function(){return this.getItemByKey("name")},getDesc:function(){return this.getItemByKey("desc")},getPrice:function(){var t=this.item?this.item.price:null;return t?this.utils.currencyPrice(t):"0"},getPromos:function(){return!(!this.item||!this.item.product)&&!(!this.item.product.promos||!this.item.product.promos.length)}},methods:{getItemByKey:function(t){var e="",s=this.item;return s&&s.product&&(e=s.product[t]),e}}}),o={render:function(){var t=this,e=t.$createElement,i=t._self._c||e;return i("div",{staticClass:"container"},[i("img",{staticClass:"photo",attrs:{src:t.getPhotoUrl}}),t._v(" "),i("div",{staticClass:"right-wrapper"},[i("div",{staticClass:"product-header"},[t.getPromos?i("img",{staticClass:"image",attrs:{src:s("WnUa")}}):t._e(),t._v(" "),i("label",{staticClass:"title"},[t._v(t._s(t.getTitle))])]),t._v(" "),i("label",{staticClass:"subtitle"},[t._v(t._s(t.item.property))]),t._v(" "),i("div",{staticClass:"desc-wrapper"},[i("label",{staticClass:"price"},[t._v("￥"+t._s(t.getPrice))]),t._v(" "),i("label",{staticClass:"count"},[t._v("x"+t._s(t.item.amount))])])])])},staticRenderFns:[]};var a=s("VU/8")(c,o,!1,function(t){s("X2rD")},"data-v-c7e5c150",null).exports,n=(s("c+FZ"),s("NYxO")),l={computed:r()({},Object(n.e)({cartGoods:function(t){return t.cart.cartGoods}}),{countDesc:function(){return"共"+(this.cartGoods&&this.cartGoods.length?this.cartGoods.length:0)+"件"}}),components:{GoodsItem:a},created:function(){},methods:{goBack:function(){this.$router.go(-1)}}},u={render:function(){var t=this.$createElement,e=this._self._c||t;return e("div",{staticClass:"container"},[e("mt-header",{staticClass:"header",attrs:{fixed:"",title:"商品清单"}},[e("header-item",{attrs:{slot:"left",isBack:!0},on:{onclick:this.goBack},slot:"left"}),this._v(" "),e("header-item",{attrs:{slot:"right",title:this.countDesc},slot:"right"})],1),this._v(" "),e("div",{staticClass:"list"},this._l(this.cartGoods,function(t,s){return e("goods-item",{key:s,staticClass:"item",attrs:{item:t}})}))],1)},staticRenderFns:[]};var d=s("VU/8")(l,u,!1,function(t){s("yIcn")},"data-v-d2487092",null);e.default=d.exports},yIcn:function(t,e){}});
//# sourceMappingURL=41.33aa1647098d6519e4fa.js.map