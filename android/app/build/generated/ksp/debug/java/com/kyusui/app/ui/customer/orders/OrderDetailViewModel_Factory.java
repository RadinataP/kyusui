package com.kyusui.app.ui.customer.orders;

import androidx.lifecycle.SavedStateHandle;
import com.kyusui.app.domain.repository.CustomerRepository;
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
public final class OrderDetailViewModel_Factory implements Factory<OrderDetailViewModel> {
  private final Provider<CustomerRepository> customerRepositoryProvider;

  private final Provider<SavedStateHandle> savedStateHandleProvider;

  public OrderDetailViewModel_Factory(Provider<CustomerRepository> customerRepositoryProvider,
      Provider<SavedStateHandle> savedStateHandleProvider) {
    this.customerRepositoryProvider = customerRepositoryProvider;
    this.savedStateHandleProvider = savedStateHandleProvider;
  }

  @Override
  public OrderDetailViewModel get() {
    return newInstance(customerRepositoryProvider.get(), savedStateHandleProvider.get());
  }

  public static OrderDetailViewModel_Factory create(
      Provider<CustomerRepository> customerRepositoryProvider,
      Provider<SavedStateHandle> savedStateHandleProvider) {
    return new OrderDetailViewModel_Factory(customerRepositoryProvider, savedStateHandleProvider);
  }

  public static OrderDetailViewModel newInstance(CustomerRepository customerRepository,
      SavedStateHandle savedStateHandle) {
    return new OrderDetailViewModel(customerRepository, savedStateHandle);
  }
}
