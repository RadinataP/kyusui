package com.kyusui.app.ui.customer.products;

import androidx.lifecycle.SavedStateHandle;
import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import javax.inject.Provider;

@ScopeMetadata
@QualifierMetadata
@DaggerGenerated
@Generated(
    value = "dagger.internal.codegen.ComponentProcessor",
    comments = "https://dagger.dev"
)
@SuppressWarnings({
    "unchecked",
    "rawtypes",
    "KotlinInternal",
    "KotlinInternalInJava"
})
public final class ProductDetailViewModel_Factory implements Factory<ProductDetailViewModel> {
  private final Provider<SavedStateHandle> savedStateHandleProvider;

  public ProductDetailViewModel_Factory(Provider<SavedStateHandle> savedStateHandleProvider) {
    this.savedStateHandleProvider = savedStateHandleProvider;
  }

  @Override
  public ProductDetailViewModel get() {
    return newInstance(savedStateHandleProvider.get());
  }

  public static ProductDetailViewModel_Factory create(
      Provider<SavedStateHandle> savedStateHandleProvider) {
    return new ProductDetailViewModel_Factory(savedStateHandleProvider);
  }

  public static ProductDetailViewModel newInstance(SavedStateHandle savedStateHandle) {
    return new ProductDetailViewModel(savedStateHandle);
  }
}
