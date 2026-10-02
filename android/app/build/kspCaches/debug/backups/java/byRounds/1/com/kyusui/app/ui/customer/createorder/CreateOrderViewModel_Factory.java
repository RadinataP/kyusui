package com.kyusui.app.ui.customer.createorder;

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
public final class CreateOrderViewModel_Factory implements Factory<CreateOrderViewModel> {
  private final Provider<CustomerRepository> customerRepositoryProvider;

  public CreateOrderViewModel_Factory(Provider<CustomerRepository> customerRepositoryProvider) {
    this.customerRepositoryProvider = customerRepositoryProvider;
  }

  @Override
  public CreateOrderViewModel get() {
    return newInstance(customerRepositoryProvider.get());
  }

  public static CreateOrderViewModel_Factory create(
      Provider<CustomerRepository> customerRepositoryProvider) {
    return new CreateOrderViewModel_Factory(customerRepositoryProvider);
  }

  public static CreateOrderViewModel newInstance(CustomerRepository customerRepository) {
    return new CreateOrderViewModel(customerRepository);
  }
}
